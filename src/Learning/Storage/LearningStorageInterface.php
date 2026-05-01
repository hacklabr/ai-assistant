<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

interface LearningStorageInterface
{
    /**
     * Save a tool pattern.
     */
    public function saveToolPattern(ToolPattern $pattern): void;

    /**
     * Get patterns for a specific tool.
     *
     * @return ToolPattern[]
     */
    public function getToolPatterns(string $toolName): array;

    /**
     * Save a bug report.
     */
    public function saveBug(BugReport $bug): string;

    /**
     * Get a bug by ID.
     */
    public function getBug(string $id): ?BugReport;

    /**
     * Search bugs by criteria.
     *
     * @return BugReport[]
     */
    public function searchBugs(array $criteria): array;

    /**
     * Search patterns by query.
     *
     * @return ToolPattern[]
     */
    public function searchPatterns(string $query): array;

    /**
     * Get all tool names with patterns.
     *
     * @return string[]
     */
    public function getToolNames(): array;

    /**
     * Save a contextual learning entry.
     */
    public function saveLearning(LearningEntry $entry): void;

    /**
     * Get all learning entries for a specific context.
     *
     * @return LearningEntry[]
     */
    public function getLearnings(string $context): array;

    /**
     * Search learning entries by query across all contexts.
     *
     * @return LearningEntry[]
     */
    public function searchLearnings(string $query): array;

    /**
     * Get all contexts that have learning entries.
     *
     * @return string[]
     */
    public function getContexts(): array;
}
