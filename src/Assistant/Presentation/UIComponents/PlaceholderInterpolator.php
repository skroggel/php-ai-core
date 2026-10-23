<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents;

/**
 * Class PlaceholderInterpolator
 *
 * Resolves controlled scalar placeholders in component action templates.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PlaceholderInterpolator
{
    /**
     * Interpolates scalar values using dot-separated paths.
     *
     * @param string $template Template text.
     * @param array<string,mixed> $values Placeholder values.
     * @param array<int,string> $allowedPlaceholders Allowed paths. Empty means all supplied scalar paths.
     * @return string Interpolated text.
     */
    public function interpolate(string $template, array $values, array $allowedPlaceholders = []): string
    {
        return (string)preg_replace_callback(
            '/{{\\s*([A-Za-z0-9_.-]+)\\s*}}/',
            function (array $matches) use ($values, $allowedPlaceholders): string {
                $path = $matches[1];
                if ($allowedPlaceholders !== [] && !in_array($path, $allowedPlaceholders, true)) {
                    return $matches[0];
                }

                $value = $this->resolve($values, $path);

                return is_scalar($value) ? (string)$value : $matches[0];
            },
            $template,
        );
    }

    /**
     * Resolves one dot-separated value path.
     *
     * @param array<string,mixed> $values Values.
     * @param string $path Path.
     * @return mixed Resolved value.
     */
    private function resolve(array $values, string $path): mixed
    {
        $value = $values;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
