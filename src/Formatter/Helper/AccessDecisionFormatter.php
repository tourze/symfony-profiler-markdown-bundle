<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter\Helper;

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

final class AccessDecisionFormatter
{
    use ValueConverterTrait;

    /**
     * 安全地从Data对象中提取值
     */
    private function getValue(mixed $data): mixed
    {
        return $data instanceof Data ? $data->getValue(true) : $data;
    }

    private const MAX_DECISIONS_DISPLAY = 10;
    private const MAX_VOTERS_DISPLAY = 3;

    /**
     * @return array<int, string>
     */
    public function format(SecurityDataCollector $collector): array
    {
        $accessLog = $this->extractAccessLog($collector);
        if ([] === $accessLog) {
            return [];
        }

        $markdown = [
            '### Access Control Decisions',
            '',
            '| Resource | Attributes | Result | Voter Decisions |',
            '|----------|------------|--------|-----------------|',
        ];

        $displayDecisions = array_slice($accessLog, 0, self::MAX_DECISIONS_DISPLAY);
        foreach ($displayDecisions as $decision) {
            $markdown[] = $this->formatAccessDecision($decision);
        }

        if (count($accessLog) > self::MAX_DECISIONS_DISPLAY) {
            $remaining = count($accessLog) - self::MAX_DECISIONS_DISPLAY;
            $markdown[] = '';
            $markdown[] = "_... and {$remaining} more access decisions_";
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function extractAccessLog(SecurityDataCollector $collector): array
    {
        $accessLog = $this->getValue($collector->getAccessDecisionLog());

        if (!is_array($accessLog) || 0 === count($accessLog)) {
            return [];
        }

        $typedAccessLog = [];
        foreach ($accessLog as $entry) {
            if (is_array($entry)) {
                $typedAccessLog[] = $entry;
            }
        }

        return $typedAccessLog;
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function formatAccessDecision(array $decision): string
    {
        $result = $this->getDecisionResult($decision);
        $object = $this->formatDecisionObject($decision);
        $attributes = $this->getDecisionAttributes($decision);
        $voterDetails = $this->formatVoterDetails($decision);

        return "| {$this->truncate($object, 30)} | {$attributes} | {$result} | {$voterDetails} |";
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function getDecisionResult(array $decision): string
    {
        return (null !== $decision['result'] && '' !== $decision['result']) ? '✅ Granted' : '❌ Denied';
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function formatDecisionObject(array $decision): string
    {
        if (!isset($decision['object'])) {
            return 'N/A';
        }

        $objectValue = $this->getValue($decision['object']);

        return match (true) {
            is_array($objectValue) => $this->convertArrayToString($objectValue),
            is_object($objectValue) => get_class($objectValue),
            default => $this->convertToString($objectValue),
        };
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function getDecisionAttributes(array $decision): string
    {
        return isset($decision['attributes']) ? implode(', ', (array) $decision['attributes']) : '';
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function formatVoterDetails(array $decision): string
    {
        $voterDetailsData = $this->extractVoterDetailsData($decision);
        if ([] === $voterDetailsData) {
            return '';
        }

        $voterDetails = $this->buildVoterDetailsList($voterDetailsData);

        return implode(', ', array_slice($voterDetails, 0, self::MAX_VOTERS_DISPLAY));
    }

    /**
     * @param array<string, mixed> $decision
     * @return array<array<string, mixed>>
     */
    private function extractVoterDetailsData(array $decision): array
    {
        if (!isset($decision['voter_details'])) {
            return [];
        }

        $voterDetailsData = $this->getValue($decision['voter_details']);

        if (!is_array($voterDetailsData) || [] === $voterDetailsData) {
            return [];
        }

        $typedVoterDetailsData = [];
        foreach ($voterDetailsData as $entry) {
            if (is_array($entry)) {
                $typedVoterDetailsData[] = $entry;
            }
        }

        return $typedVoterDetailsData;
    }

    /**
     * @param array<array<string, mixed>> $voterDetailsData
     * @return array<int, string>
     */
    private function buildVoterDetailsList(array $voterDetailsData): array
    {
        $voterDetails = [];
        foreach ($voterDetailsData as $voter) {
            $voterName = $this->getVoterName($voter);
            $vote = $this->getVoterVote($voter);
            $voterDetails[] = $voterName . ': ' . $vote;
        }

        return $voterDetails;
    }

    /**
     * @param array<string, mixed> $voter
     */
    private function getVoterName(array $voter): string
    {
        if (!isset($voter['class'])) {
            return 'Unknown';
        }

        return $this->getShortClassName($this->convertToString($voter['class']));
    }

    /**
     * @param array<string, mixed> $voter
     */
    private function getVoterVote(array $voter): string
    {
        return isset($voter['vote']) ? $this->formatVote($voter['vote']) : '?';
    }

    private function formatVote(mixed $vote): string
    {
        return match ($vote) {
            1 => '✅ Grant',
            -1 => '❌ Deny',
            0 => '⏭️ Abstain',
            default => $this->convertToString($vote),
        };
    }
}
