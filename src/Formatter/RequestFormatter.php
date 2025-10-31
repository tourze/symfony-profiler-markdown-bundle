<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

class RequestFormatter extends AbstractMarkdownFormatter
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof RequestDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof RequestDataCollector) {
            return [];
        }

        $markdown = [
            '## 📨 Request & Response',
            '',
        ];

        $markdown = array_merge($markdown, $this->formatOverview($collector));
        $markdown = array_merge($markdown, $this->formatRequestAttributes($collector));
        $markdown = array_merge($markdown, $this->formatRequestHeaders($collector));
        $markdown = array_merge($markdown, $this->formatQueryParameters($collector));
        $markdown = array_merge($markdown, $this->formatPostData($collector));
        $markdown = array_merge($markdown, $this->formatCookies($collector));
        $markdown = array_merge($markdown, $this->formatSession($collector));
        $markdown = array_merge($markdown, $this->formatResponseHeaders($collector));

        return array_merge($markdown, $this->formatFlashMessages($collector));
    }

    /**
     * @return array<int, string>
     */
    private function formatOverview(RequestDataCollector $collector): array
    {
        $markdown = [
            '### Overview',
            '',
            '| Property | Value |',
            '|----------|-------|',
            '| **Route** | ' . $this->stringifyValue($this->getValue($collector->getRoute())) . ' |',
            '| **Controller** | `' . $this->formatController($collector->getController()) . '` |',
            '| **Method** | ' . $collector->getMethod() . ' |',
            '| **Path** | ' . $collector->getPathInfo() . ' |',
            '| **Format** | ' . $collector->getFormat() . ' |',
            '| **Locale** | ' . $collector->getLocale() . ' |',
            '| **Status Code** | ' . $this->formatStatusCode($collector->getStatusCode()) . ' |',
        ];

        $redirect = $collector->getRedirect();
        if (is_array($redirect) || $redirect instanceof Data) {
            $markdown[] = '| **Redirect** | ' . $redirect['location'] . ' (' . $redirect['status_code'] . ') |';
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatRequestAttributes(RequestDataCollector $collector): array
    {
        $attributes = $collector->getRequestAttributes();
        if (0 === count($attributes->all())) {
            return [];
        }

        return [
            '### Request Attributes',
            '',
            '<details>',
            '<summary>Show attributes</summary>',
            '',
            '```json',
            $this->formatJson($attributes->all()),
            '```',
            '</details>',
            '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formatRequestHeaders(RequestDataCollector $collector): array
    {
        $requestHeaders = $collector->getRequestHeaders()->all();
        if ([] === $requestHeaders) {
            return [];
        }

        $markdown = [
            '### Request Headers',
            '',
            '<details>',
            '<summary>Show headers</summary>',
            '',
            '| Header | Value |',
            '|--------|-------|',
        ];

        foreach ($requestHeaders as $header => $value) {
            $value = $this->getValue($value);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $markdown[] = '| ' . $header . ' | ' . $this->truncate($this->stringifyValue($value), 100) . ' |';
        }

        $markdown[] = '</details>';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatQueryParameters(RequestDataCollector $collector): array
    {
        $query = $collector->getRequestQuery();
        if (0 === count($query->all())) {
            return [];
        }

        return [
            '### Query Parameters',
            '',
            '```json',
            $this->formatJson($query->all()),
            '```',
            '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formatPostData(RequestDataCollector $collector): array
    {
        $post = $collector->getRequestRequest();
        if (0 === count($post->all())) {
            return [];
        }

        return [
            '### POST Data',
            '',
            '```json',
            $this->formatJson($post->all()),
            '```',
            '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formatCookies(RequestDataCollector $collector): array
    {
        $cookies = $collector->getRequestCookies();
        if (0 === count($cookies->all())) {
            return [];
        }

        $markdown = [
            '### Cookies',
            '',
            '<details>',
            '<summary>Show cookies</summary>',
            '',
        ];

        foreach ($cookies->all() as $name => $value) {
            $markdown[] = '- **' . $name . '**: ' . $this->truncate($this->stringifyValue($this->getValue($value)), 50);
        }

        $markdown[] = '</details>';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatSession(RequestDataCollector $collector): array
    {
        $session = $collector->getSessionAttributes();
        if ([] === $session) {
            return [];
        }

        return [
            '### Session Data',
            '',
            '<details>',
            '<summary>Show session</summary>',
            '',
            '```json',
            $this->formatJson($session),
            '```',
            '</details>',
            '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formatResponseHeaders(RequestDataCollector $collector): array
    {
        $responseHeaders = $collector->getResponseHeaders()->all();
        if ([] === $responseHeaders) {
            return [];
        }

        $markdown = [
            '### Response Headers',
            '',
            '<details>',
            '<summary>Show headers</summary>',
            '',
            '| Header | Value |',
            '|--------|-------|',
        ];

        foreach ($responseHeaders as $header => $value) {
            $value = $this->getValue($value);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $markdown[] = '| ' . $header . ' | ' . $this->truncate($this->stringifyValue($value), 100) . ' |';
        }

        $markdown[] = '</details>';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatFlashMessages(RequestDataCollector $collector): array
    {
        $flashes = $collector->getFlashes();
        if ([] === $flashes) {
            return [];
        }

        $markdown = [
            '### Flash Messages',
            '',
        ];

        foreach ($flashes as $type => $messages) {
            foreach ($messages as $message) {
                $markdown[] = '- **' . $type . '**: ' . $this->stringifyValue($this->getValue($message));
            }
        }
        $markdown[] = '';

        return $markdown;
    }

    private function formatController(mixed $controller): string
    {
        $controller = $this->getValue($controller);

        if (is_array($controller)) {
            if (isset($controller['class'], $controller['method'])) {
                return $this->stringifyValue($controller['class']) . '::' . $this->stringifyValue($controller['method']);
            }

            $encoded = json_encode($controller);

            return false !== $encoded ? $encoded : 'N/A';
        }

        if (is_string($controller)) {
            return $controller;
        }

        return 'N/A';
    }

    private function formatStatusCode(int $code): string
    {
        $emoji = match (true) {
            $code >= 200 && $code < 300 => '✅',
            $code >= 300 && $code < 400 => '↩️',
            $code >= 400 && $code < 500 => '⚠️',
            $code >= 500 => '❌',
            default => '',
        };

        return "{$emoji} {$code}";
    }

    public function getPriority(): int
    {
        return 100; // High priority as it's often the most important
    }

    private function stringifyValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return false !== json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return get_class($value);
        }

        return '';
    }
}
