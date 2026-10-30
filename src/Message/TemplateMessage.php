<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Approved message template.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class TemplateMessage implements OutgoingMessage
{
    /**
     * Creates a template message with body/header/button parameters or header media.
     *
     * @param list<string> $bodyParameters
     * @param list<string> $headerParameters
     * @param list<string> $buttonParameters
     */
    public function __construct(
        public string $name,
        public string $language,
        public array $bodyParameters = [],
        public array $headerParameters = [],
        public array $buttonParameters = [],
        public ?MediaSource $headerMedia = null,
        public string $languagePolicy = 'deterministic',
    ) {
    }

    /**
     * Returns the template message type.
     */
    public function type(): MessageType
    {
        return MessageType::Template;
    }

    /**
     * Returns the Graph `template` field with header, body and quick-reply button components.
     */
    public function graphFields(): array
    {
        $components = [];

        if ($this->headerMedia instanceof MediaSource) {
            $components[] = [
                'type'       => 'header',
                'parameters' => [[
                    'type'  => 'image',
                    'image' => $this->headerMedia->toGraphField(),
                ]],
            ];
        } elseif ($this->headerParameters !== []) {
            $components[] = [
                'type'       => 'header',
                'parameters' => $this->textParameters($this->headerParameters),
            ];
        }

        if ($this->bodyParameters !== []) {
            $components[] = [
                'type'       => 'body',
                'parameters' => $this->textParameters($this->bodyParameters),
            ];
        }

        foreach ($this->buttonParameters as $index => $parameter) {
            $components[] = [
                'type'       => 'button',
                'sub_type'   => 'quick_reply',
                'index'      => (string) $index,
                'parameters' => [[
                    'type'    => 'payload',
                    'payload' => $parameter,
                ]],
            ];
        }

        $template = [
            'name'     => $this->name,
            'language' => [
                'code'   => $this->language,
                'policy' => $this->languagePolicy,
            ],
        ];

        if ($components !== []) {
            $template['components'] = $components;
        }

        return ['template' => $template];
    }

    /**
     * @param list<string> $values
     *
     * @return list<array{type: string, text: string}>
     */
    private function textParameters(array $values): array
    {
        $parameters = [];
        foreach ($values as $value) {
            $parameters[] = [
                'type' => 'text',
                'text' => $value,
            ];
        }

        return $parameters;
    }
}
