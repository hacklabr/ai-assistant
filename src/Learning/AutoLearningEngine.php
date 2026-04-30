<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning;

/**
 * Central auto-learning engine combining all learning components.
 */
class AutoLearningEngine
{
    public function __construct(
        private readonly ToolLearner $toolLearner,
        private readonly BugCollector $bugCollector,
        private readonly SuggestionEngine $suggestionEngine,
    ) {}

    public function getToolLearner(): ToolLearner
    {
        return $this->toolLearner;
    }

    public function getBugCollector(): BugCollector
    {
        return $this->bugCollector;
    }

    public function getSuggestionEngine(): SuggestionEngine
    {
        return $this->suggestionEngine;
    }
}
