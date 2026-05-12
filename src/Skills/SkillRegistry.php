<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Skills;

/**
 * Registry for managing available skills.
 */
class SkillRegistry
{
    /**
     * @var array<string, Skill>
     */
    private array $skills = [];

    private readonly MarkdownSkillLoader $loader;

    public function __construct(
        private readonly ?string $skillsPath = null,
        ?MarkdownSkillLoader $loader = null,
    ) {
        $this->loader = $loader ?? new MarkdownSkillLoader();
    }

    /**
     * Load all skills from the skills directory.
     */
    public function loadAll(): void
    {
        if ($this->skillsPath === null || !is_dir($this->skillsPath)) {
            return;
        }

        $skills = $this->loader->loadDirectory($this->skillsPath);
        foreach ($skills as $skill) {
            $this->register($skill);
        }
    }

    /**
     * Register a skill programmatically.
     */
    public function register(Skill $skill): void
    {
        $this->skills[$skill->name] = $skill;
    }

    /**
     * Get a skill by name.
     */
    public function get(string $name): ?Skill
    {
        return $this->skills[$name] ?? null;
    }

    /**
     * Check if skill exists.
     */
    public function has(string $name): bool
    {
        return isset($this->skills[$name]);
    }

    /**
     * Get all registered skills.
     *
     * @return Skill[]
     */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * Get skills by category.
     *
     * @return Skill[]
     */
    public function byCategory(string $category): array
    {
        return array_filter(
            $this->skills,
            fn (Skill $skill) => in_array($category, $skill->categories, true)
        );
    }

    /**
     * Find skills matching a search query.
     *
     * @return Skill[]
     */
    public function search(string $query): array
    {
        $queryLower = strtolower($query);

        return array_filter(
            $this->skills,
            function (Skill $skill) use ($queryLower) {
                return str_contains(strtolower($skill->name), $queryLower)
                    || str_contains(strtolower($skill->description), $queryLower)
                    || str_contains(strtolower($skill->content), $queryLower);
            }
        );
    }
}
