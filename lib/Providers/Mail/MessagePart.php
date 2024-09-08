<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Providers\Mail;

class MessagePart
{
    protected array $_parameters = [];
    protected array $_parts = [];

    public function __construct(array $parameters = []) {
        $this->setParameters($parameters);
    }

    /* Custom Functions */

    public function setParameters(array $parameters) {
        
        // replace parameters store
        $this->_parameters = $parameters;
        // determine if parameters contains subparts
        // if subParts exist convert them to a MessagePart object
        // and remove subParts parameter
        if (is_array($this->_parameters['subParts'])) {
            foreach ($this->_parameters['subParts'] as $key => $entry) {
                if (is_object($entry)) {
                    $entry = get_object_vars($entry);
                }
                $this->_parts[] = new MessagePart($entry);
            }
            unset($this->_parameters['subParts']);
        }
    }

    public function getParameters(): array {

        // copy parameters store
        $parameters = $this->_parameters;
        // determine if this MessagePart has any sub MessageParts
        // if sub MessageParts exist retrieve sub MessagePart parameters
        // and add them to the subParts parameters, otherwise set the subParts parameter to nothing
        if (count($this->_parts) > 0) {
            $parameters['subParts'] = [];
            foreach ($this->_parts as $entry) {
                $parameters['subParts'][] = $entry->getParameters();
            }
        } else {
            $parameters['subParts'] = null;
        }
        // return part parameters
        return $parameters;
		
	}

    public function setBlobId(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['blobId'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getBlobId(): string|null {
        
        // return value of parameter
        return $this->_parameters['blobId'] ?? null;

    }
    
    /* Common Functions */ 

    public function setId(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['partId'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getId(): string|null {
        
        // return value of parameter
        return $this->_parameters['partId'] ?? null;

    }

    public function setType(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['type'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getType(): string|null {
        
        // return value of parameter
        return $this->_parameters['type'] ?? null;

    }

    public function setDisposition(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['disposition'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getDisposition(): string|null {
        
        // return value of parameter
        return $this->_parameters['disposition'] ?? null;

    }

    public function setName(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['name'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getName(): string|null {
        
        // return value of parameter
        return $this->_parameters['name'] ?? null;

    }

    public function setCharset(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['charset'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getCharset(): string|null {
        
        // return value of parameter
        return $this->_parameters['charset'] ?? null;

    }

    public function setLanguage(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['language'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getLanguage(): string|null {
        
        // return value of parameter
        return $this->_parameters['language'] ?? null;

    }

    public function setLocation(string $value): self {
        
        // creates or updates parameter and assigns value
        $this->_parameters['location'] = $value;
        // return self for function chaining
        return $this;

    }

    public function getLocation(): string|null {
        
        // return value of parameter
        return $this->_parameters['location'] ?? null;

    }

    public function setParts(MessagePart ...$value): self {
        
        // creates or updates parameter and assigns value
        $this->_parts = $value;
        // return self for function chaining
        return $this;

    }

    public function getParts(): array {
        
        // return value of parameter
        return $this->_parts;

    }

}
