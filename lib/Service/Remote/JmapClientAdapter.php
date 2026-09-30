<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service\Remote;

use OCP\Http\Client\IClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adapts the Native HTTP client (IClient) to the PSR-18 (ClientInterface) contract.
 *
 * Nextcloud 34+ already implements ClientInterface on IClient; this wrapper
 * keeps the integration working on the older supported releases (32/33) and
 * pins the per-request options the JMAP transport relies on.
 */
class JmapClientAdapter implements ClientInterface {

	/**
	 * @param array<string, mixed> $defaultOptions Options applied to every
	 *                                             request, e.g. 'verify' and 'timeout'. SSRF protection stays on
	 *                                             unless explicitly disabled here.
	 */
	public function __construct(
		private IClient $client,
		private ResponseFactoryInterface $responseFactory,
		private StreamFactoryInterface $streamFactory,
		private array $defaultOptions = [],
		private ?LoggerInterface $logger = null,
	) {
	}

	#[\Override]
	public function sendRequest(RequestInterface $request): ResponseInterface {
		// convert PSR-7 request to native transport client request
		$options = $this->defaultOptions;
		foreach (array_keys($request->getHeaders()) as $name) {
			$options['headers'][$name] = $request->getHeaderLine($name);
		}
		$options['allow_redirects'] = false;
		$options['http_errors'] = false;
		// Always request a streamed response so the body is exposed as a live
		// resource rather than buffered into memory. Small responses are read
		// in full by the caller; large blob downloads stay streamed end-to-end.
		$options['stream'] = true;

		$body = (string)$request->getBody();
		if ($body !== '') {
			$options['body'] = $body;
		}

		$this->logMessage($request);

		// transceive and catch any transport-level exceptions
		try {
			$nativeResponse = $this->client->request(
				$request->getMethod(),
				(string)$request->getUri(),
				$options,
			);
		} catch (\Throwable $e) {
			$this->logger?->debug('JMAPC Request failed', ['exception' => $e::class]);
			throw new JmapTransportException($e->getMessage(), (int)$e->getCode(), $e);
		}

		// Convert the native transport client response to a PSR-7 response.
		$body = $nativeResponse->getBody();
		$stream = is_resource($body)
			? $this->streamFactory->createStreamFromResource($body)
			: $this->streamFactory->createStream((string)$body);

		$response = $this->responseFactory
			->createResponse($nativeResponse->getStatusCode())
			->withBody($stream);

		foreach ($nativeResponse->getHeaders() as $name => $values) {
			$response = $response->withHeader($name, $values);
		}

		$this->logMessage($request, $response);
		return $response;
	}

	private function logMessage(RequestInterface $request, ?ResponseInterface $response = null): void {
		if ($this->logger === null) {
			return;
		}
		$message = $response ?? $request;
		$uri = $request->getUri()->withUserInfo('');
		parse_str($uri->getQuery(), $query);
		$uri = $uri->withQuery(http_build_query($this->redact($query)));
		$context = [
			'method' => $request->getMethod(),
			'uri' => (string)$uri,
		];
		if ($response !== null) {
			$context['status'] = $response->getStatusCode();
		}
		$context['headers'] = [];
		foreach ($message->getHeaders() as $name => $values) {
			$context['headers'][$name] = $this->isSensitive($name) ? '[redacted]' : implode(', ', $values);
		}
		$context['body'] = $this->logBody($message);
		$this->logger->debug($response === null ? 'JMAPC Request' : 'JMAPC Response', $context);
	}

	private function logBody(MessageInterface $message): mixed {
		$stream = $message->getBody();
		if (!$stream->isSeekable()) {
			return '[streamed body]';
		}
		$contentType = strtolower($message->getHeaderLine('Content-Type'));
		if (!str_contains($contentType, 'json')) {
			return '[non-JSON body]';
		}
		$position = $stream->tell();
		try {
			$stream->rewind();
			$body = $stream->read(1048577);
		} finally {
			$stream->seek($position);
		}
		if ($body === '') {
			return null;
		}
		if (strlen($body) > 1048576) {
			return '[body exceeds 1 MiB]';
		}
		$value = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return '[invalid JSON body]';
		}
		return $this->redact($value);
	}

	private function redact(mixed $value): mixed {
		if (!is_array($value)) {
			return $value;
		}
		foreach ($value as $key => $item) {
			$value[$key] = $this->isSensitive((string)$key) ? '[redacted]' : $this->redact($item);
		}
		return $value;
	}

	private function isSensitive(string $name): bool {
		return (bool)preg_match('/authorization|cookie|password|secret|token/i', $name);
	}
}
