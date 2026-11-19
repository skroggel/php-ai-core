<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 */

namespace Madj2k\AiCore\Tests\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Glossary\Glossary;
use Madj2k\AiCore\Assistant\Glossary\GlossaryProviderInterface;
use Madj2k\AiCore\Assistant\Prompt\Context\Builder\GlossaryContextBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Verifies generic glossary prompt context generation.
 */
final class GlossaryContextBuilderTest extends TestCase
{
    /**
     * Ensures source-to-target mappings are included in answer prompts.
     *
     * @return void
     */
    public function testAddsSourceToTargetGlossaryForAnswerStep(): void
    {
        $provider = $this->createStub(GlossaryProviderInterface::class);
        $provider->method('resolve')->willReturn(new Glossary(
            'food-terminology',
            'Food terminology',
            "source term: natural flavour\ntarget term: natürliches Aroma",
            'de',
        ));
        $step = $this->createStub(PipelineStepConfigurationInterface::class);
        $step->method('getType')->willReturn(AssistantPipelineProcessorType::AnswerGenerator);
        $context = new Context(
            new AssistantContext(),
            new Request('question', 'chat-id'),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );

        $sections = (new GlossaryContextBuilder($provider))->build($context, $step);

        self::assertCount(1, $sections);
        self::assertStringContainsString('food-terminology', $sections[0]->title);
        self::assertStringContainsString('natural flavour', $sections[0]->content);
        self::assertStringContainsString('natürliches Aroma', $sections[0]->content);
        self::assertStringContainsString('de', $sections[0]->content);
    }
}
