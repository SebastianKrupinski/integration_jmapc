<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Logging;

use OCP\ILogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

final class FileLogger implements LoggerInterface {
	public function __construct(
		private string $path,
	) {
	}

	#[\Override]
	public function emergency(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::FATAL, $message, $context);
	}

	#[\Override]
	public function alert(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message, $context);
	}

	#[\Override]
	public function critical(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message, $context);
	}

	#[\Override]
	public function error(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message, $context);
	}

	#[\Override]
	public function warning(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::WARN, $message, $context);
	}

	#[\Override]
	public function notice(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::INFO, $message, $context);
	}

	#[\Override]
	public function info(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::INFO, $message, $context);
	}

	#[\Override]
	public function debug(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::DEBUG, $message, $context);
	}

	#[\Override]
	public function log($level, $message, array $context = []): void {
		if (is_string($level)) {
			$level = match ($level) {
				LogLevel::EMERGENCY => ILogger::FATAL,
				LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR => ILogger::ERROR,
				LogLevel::WARNING => ILogger::WARN,
				LogLevel::NOTICE, LogLevel::INFO => ILogger::INFO,
				LogLevel::DEBUG => ILogger::DEBUG,
				default => null,
			};
		}

		if (!is_int($level)) {
			throw new InvalidArgumentException('Unsupported custom log level');
		}

		$this->write($level, $message, $context);
	}

	private function write(int $level, string|Stringable $message, array $context): void {
		$entry = [
			'time' => (new \DateTimeImmutable())->format('Y-m-d\\TH:i:s.vP'),
			'level' => $level,
			'app' => 'integration_jmapc',
			'message' => (string)$message,
		];
		if ($context !== []) {
			$entry['data'] = $context;
		}
		$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
		if ($line === false) {
			return;
		}

		$handle = @fopen($this->path, 'ab');
		if ($handle === false) {
			error_log('JMAPC: Unable to open the transmission log.');
			return;
		}

		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			return;
		}

		fwrite($handle, $line . "\n");

		flock($handle, LOCK_UN);
		fclose($handle);
	}
}
