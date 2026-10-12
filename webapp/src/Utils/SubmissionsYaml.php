<?php declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The example submission requirements of a problem package, see
 * https://icpc.io/problem-package-format/spec/2025-09.html#example-submissions
 */
class SubmissionsYaml
{
    private const VERDICTS = [
        'AC'  => 'CORRECT',
        'WA'  => 'WRONG-ANSWER',
        'TLE' => 'TIMELIMIT',
        'RTE' => 'RUN-ERROR',
    ];

    private const DEFAULT_RULES = [
        'accepted'            => ['permitted' => ['AC']],
        'rejected'            => ['required' => ['RTE', 'TLE', 'WA']],
        'wrong_answer'        => ['permitted' => ['AC', 'WA'], 'required' => ['WA']],
        'time_limit_exceeded' => ['permitted' => ['AC', 'TLE'], 'required' => ['TLE']],
        'run_time_error'      => ['permitted' => ['AC', 'RTE'], 'required' => ['RTE']],
        'brute_force'         => ['permitted' => ['AC', 'RTE', 'TLE'], 'required' => ['RTE', 'TLE']],
    ];

    // Not checkable on a judging.
    private const IGNORED_KEYS = ['authors', 'model_solution', 'message'];

    // Likely mix-ups, e.g. from submissions.json.
    private const KEY_SUGGESTIONS = [
        'entry_point' => 'entrypoint',
        'langid'      => 'language',
        'lang'        => 'language',
        'author'      => 'authors',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $rules = self::DEFAULT_RULES;

    /**
     * @param array<string, string[]> $messages
     */
    public function __construct(?string $yaml, array &$messages)
    {
        if ($yaml === null) {
            return;
        }
        try {
            $data = Yaml::parse($yaml);
        } catch (ParseException $e) {
            $messages['danger'][] = sprintf("Ignoring submissions/submissions.yaml: %s", $e->getMessage());
            return;
        }
        if ($data === null || $data === []) {
            return;
        }
        if (!is_array($data) || array_is_list($data)) {
            $messages['danger'][] = 'Ignoring submissions/submissions.yaml: not a map.';
            return;
        }

        foreach ($data as $pattern => $config) {
            $pattern = rtrim((string)$pattern, '/');
            if (!is_array($config) || ($config !== [] && array_is_list($config))) {
                $messages['danger'][] = sprintf("Ignoring '%s' in submissions/submissions.yaml: not a map.", $pattern);
                continue;
            }
            $rule = $this->parseRule($pattern, $config, $messages);
            // Only the exact directory name overrides a default, any other pattern adds a rule.
            $this->rules[$pattern] = array_merge($this->rules[$pattern] ?? [], $rule);
        }
    }

    /**
     * @param string $path Relative to submissions/, e.g. accepted/sol.py
     * @param array<string, string[]> $messages
     * @return array{results: string[]|null, score: float|null, language: string|null, entrypoint: string|null}
     */
    public function forSubmission(string $path, array &$messages): array
    {
        $candidates = [];
        $prefix = '';
        foreach (explode('/', $path) as $part) {
            $prefix .= ($prefix === '' ? '' : '/') . $part;
            $candidates[] = $prefix;
        }

        $results = null;
        $values = ['score' => [], 'language' => [], 'entrypoint' => []];
        foreach ($this->rules as $pattern => $rule) {
            if (!self::matches($pattern, $candidates)) {
                continue;
            }
            $verdicts = self::finalVerdicts($rule);
            if ($verdicts !== null) {
                $results = $results === null ? $verdicts : array_values(array_intersect($results, $verdicts));
            }
            foreach (array_keys($values) as $key) {
                if (isset($rule[$key])) {
                    $values[$key][] = $rule[$key];
                }
            }
        }

        if ($results === []) {
            $messages['danger'][] = sprintf("No verdict satisfies submissions/submissions.yaml for '%s'.", $path);
            $results = null;
        }
        foreach ($values as $key => $found) {
            if (count(array_unique($found)) > 1) {
                $messages['warning'][] = sprintf("Conflicting %s in submissions/submissions.yaml for '%s', using '%s'.",
                    $key, $path, $found[0]);
            }
        }

        return [
            'results'    => $results === null ? null : array_map(fn(string $v) => self::VERDICTS[$v], $results),
            'score'      => $values['score'][0] ?? null,
            'language'   => $values['language'][0] ?? null,
            'entrypoint' => $values['entrypoint'][0] ?? null,
        ];
    }

    /**
     * @param array<mixed> $config
     * @param array<string, string[]> $messages
     * @return array<string, mixed>
     */
    private function parseRule(string $pattern, array $config, array &$messages): array
    {
        $rule = [];
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'permitted':
                case 'required':
                    $verdicts = array_map(
                        fn($verdict) => is_string($verdict) ? mb_strtoupper(trim($verdict)) : $verdict,
                        is_array($value) ? array_values($value) : [$value]
                    );
                    $unknown = array_filter($verdicts,
                        fn($verdict) => !is_string($verdict) || !isset(self::VERDICTS[$verdict]));
                    if (!empty($unknown)) {
                        $messages['danger'][] = sprintf("Ignoring %s for '%s' in submissions/submissions.yaml: unknown verdict(s) %s.",
                            $key, $pattern, implode(', ', array_map(fn($verdict) => json_encode($verdict), $unknown)));
                        break;
                    }
                    $rule[$key] = $verdicts;
                    break;
                case 'use_for_time_limit':
                    // lower and upper are shorthands for verdict requirements.
                    if ($value === 'lower') {
                        $rule['permitted_for_time_limit'] = ['AC', 'WA', 'RTE'];
                    } elseif ($value === 'upper') {
                        $rule['required_for_time_limit'] = ['TLE'];
                    } elseif (!is_bool($value)) {
                        $messages['warning'][] = sprintf("Ignoring use_for_time_limit for '%s' in submissions/submissions.yaml: must be true, false, lower or upper.",
                            $pattern);
                    }
                    break;
                case 'score':
                    if (is_int($value) || is_float($value)) {
                        $rule['score'] = (float)$value;
                    } else {
                        $messages['warning'][] = sprintf("Ignoring score for '%s' in submissions/submissions.yaml: only a single number is supported.",
                            $pattern);
                    }
                    break;
                case 'language':
                case 'entrypoint':
                    if (is_string($value)) {
                        $rule[$key] = $value;
                    } else {
                        $messages['warning'][] = sprintf("Ignoring %s for '%s' in submissions/submissions.yaml: not a string.",
                            $key, $pattern);
                    }
                    break;
                default:
                    if (in_array($key, self::IGNORED_KEYS, true)) {
                        break;
                    }
                    // Test data groups map to requirements, anything else is a typo.
                    if (is_array($value) && !array_is_list($value)) {
                        $messages['warning'][] = sprintf("Ignoring '%s' for '%s' in submissions/submissions.yaml: requirements per test data group are not supported.",
                            $key, $pattern);
                    } else {
                        $suggestion = isset(self::KEY_SUGGESTIONS[$key])
                            ? sprintf(", did you mean '%s'?", self::KEY_SUGGESTIONS[$key]) : '.';
                        $messages['warning'][] = sprintf("Ignoring unknown key '%s' for '%s' in submissions/submissions.yaml%s",
                            $key, $pattern, $suggestion);
                    }
            }
        }
        return $rule;
    }

    /**
     * Possible final verdicts under this rule; we only see the first non-accepted test case verdict.
     *
     * @param array<string, mixed> $rule
     * @return string[]|null
     */
    private static function finalVerdicts(array $rule): ?array
    {
        $isSet = fn(?array $verdicts) => $verdicts !== null;
        $permittedSets = array_filter([$rule['permitted'] ?? null, $rule['permitted_for_time_limit'] ?? null], $isSet);
        $requiredSets = array_filter([$rule['required'] ?? null, $rule['required_for_time_limit'] ?? null], $isSet);
        if (empty($permittedSets) && empty($requiredSets)) {
            return null;
        }

        $verdicts = array_keys(self::VERDICTS);
        foreach ($permittedSets as $permitted) {
            $verdicts = array_intersect($verdicts, $permitted);
        }
        foreach ($requiredSets as $required) {
            if (empty(array_intersect($verdicts, $required))) {
                return [];
            }
            if (!in_array('AC', $required, true)) {
                $verdicts = array_diff($verdicts, ['AC']);
            }
        }
        return array_values($verdicts);
    }

    /**
     * @param string[] $candidates
     */
    private static function matches(string $pattern, array $candidates): bool
    {
        foreach (self::expandBraces($pattern) as $expanded) {
            foreach ($candidates as $candidate) {
                if (fnmatch(rtrim($expanded, '/'), $candidate, FNM_PATHNAME)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public static function expandBraces(string $pattern): array
    {
        $start = strpos($pattern, '{');
        if ($start === false) {
            return [$pattern];
        }
        $depth = 0;
        $options = [];
        $optionStart = $start + 1;
        for ($i = $start; $i < strlen($pattern); $i++) {
            $char = $pattern[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === ',' && $depth === 1) {
                $options[] = substr($pattern, $optionStart, $i - $optionStart);
                $optionStart = $i + 1;
            } elseif ($char === '}' && --$depth === 0) {
                $options[] = substr($pattern, $optionStart, $i - $optionStart);
                $prefix = substr($pattern, 0, $start);
                $suffix = substr($pattern, $i + 1);
                $expanded = [];
                foreach ($options as $option) {
                    array_push($expanded, ...self::expandBraces($prefix . $option . $suffix));
                }
                return $expanded;
            }
        }
        return [$pattern];
    }
}
