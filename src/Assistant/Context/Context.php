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

namespace Madj2k\AiCore\Assistant\Context;

use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan;
use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;

/**
 * Class Context
 *
 * Central state object shared by all pipeline steps.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Context
{
    /**
     * Assistant context.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext
     */
    protected AssistantContext $assistant;


    /**
     * Chat request.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Request\Request
     */
    protected Request $request;


    /**
     * Visible chat history.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Request\History
     */
    protected History $history;


    /**
     * Retrieval state.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult
     */
    protected RetrievalResult $retrieval;


    /**
     * Runtime retrieval selection plan.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan
     */
    protected RetrievalPlan $retrievalPlan;


    /**
     * Runtime presentationRule selection plan.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan
     */
    protected PresentationRulePlan $presentationRulePlan;


    /**
     * Answer state.
     *
     * @var \Madj2k\AiCore\Assistant\Context\Answer\AnswerState
     */
    protected AnswerState $answer;


    /**
     * Current query.
     *
     * @var string
     */
    protected string $currentQuery = '';


    /**
     * Processing trace
     *
     * @var \Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace
     */
    protected ProcessingTrace $processingTrace;


    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext $assistant Assistant context.
     * @param \Madj2k\AiCore\Assistant\Context\Request\Request $request Chat request.
     * @param \Madj2k\AiCore\Assistant\Context\Request\History $history Visible chat history.
     * @param \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult $retrieval Retrieval state.
     * @param \Madj2k\AiCore\Assistant\Context\Answer\AnswerState $answer Answer state.
     * @param \Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace $processingTrace Processing trace.
     */
    public function __construct(
        AssistantContext $assistant,
        Request          $request,
        History          $history,
        RetrievalResult  $retrieval,
        AnswerState      $answer,
        ProcessingTrace  $processingTrace
    ) {
        $this->assistant = $assistant;
        $this->request = $request;
        $this->history = $history;
        $this->retrieval = $retrieval;
        $this->retrievalPlan = new RetrievalPlan();
        $this->presentationRulePlan = new PresentationRulePlan();
        $this->answer = $answer;
        $this->currentQuery = $request->getQuery();
        $this->processingTrace = $processingTrace;
    }


    /**
     * Returns the assistant context.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext Assistant context.
     */
    public function getAssistant(): AssistantContext
    {
        return $this->assistant;
    }


    /**
     * Sets the assistant context.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext $assistant Assistant context.
     * @return void
     */
    public function setAssistant(AssistantContext $assistant): void
    {
        $this->assistant = $assistant;
    }


    /**
     * Returns the chat request.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Request\Request Chat request.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }


    /**
     * Sets the chat request.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Request\Request $request Chat request.
     * @return void
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;

        if ($this->currentQuery === '') {
            $this->currentQuery = $request->getQuery();
        }
    }


    /**
     * Returns the chat history.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Request\History Visible chat history.
     */
    public function getHistory(): History
    {
        return $this->history;
    }


    /**
     * Sets the chat history.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Request\History $history Visible chat history.
     * @return void
     */
    public function setHistory(History $history): void
    {
        $this->history = $history;
    }


    /**
     * Returns the retrieval state.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult Retrieval state.
     */
    public function getRetrieval(): RetrievalResult
    {
        return $this->retrieval;
    }


    /**
     * Sets the retrieval state.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult $retrieval Retrieval state.
     * @return void
     */
    public function setRetrieval(RetrievalResult $retrieval): void
    {
        $this->retrieval = $retrieval;
    }

    /**
     * Returns the retrieval selection plan.
     *
     * @return RetrievalPlan Retrieval plan.
     */
    public function getRetrievalPlan(): RetrievalPlan
    {
        return $this->retrievalPlan;
    }

    /**
     * Sets the retrieval selection plan.
     *
     * @param RetrievalPlan $retrievalPlan Retrieval plan.
     * @return void
     */
    public function setRetrievalPlan(RetrievalPlan $retrievalPlan): void
    {
        $this->retrievalPlan = $retrievalPlan;
    }


    /**
     * Gets the presentationRule selection plan.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan
     */
    public function getPresentationRulePlan(): PresentationRulePlan
    {
        return $this->presentationRulePlan;
    }


    /**
     * Sets the presentationRule selection plan.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan $presentationRulePlan
     * @return void
     */
    public function setPresentationRulePlan(PresentationRulePlan $presentationRulePlan): void
    {
        $this->presentationRulePlan = $presentationRulePlan;
    }


    /**
     * Returns the answer state.
     *
     * @return \Madj2k\AiCore\Assistant\Context\Answer\AnswerState Answer state.
     */
    public function getAnswer(): AnswerState
    {
        return $this->answer;
    }


    /**
     * Sets the answer state.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Answer\AnswerState $answer Answer state.
     * @return void
     */
    public function setAnswer(AnswerState $answer): void
    {
        $this->answer = $answer;
    }


    /**
     * Returns the current query.
     *
     * @return string Current query.
     */
    public function getCurrentQuery(): string
    {
        return $this->currentQuery;
    }


    /**
     * Sets the current query.
     *
     * Falls an empty value is passed, the original request query is used.
     *
     * @param string $currentQuery Current query.
     * @return void
     */
    public function setCurrentQuery(string $currentQuery): void
    {
        $currentQuery = trim($currentQuery);

        $this->currentQuery = $currentQuery !== ''
            ? $currentQuery
            : $this->request->getQuery();
    }


    /**
     * Returns the processing trace
     *
     * @return \Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace
     */
    public function getProcessingTrace (): ProcessingTrace
    {
        return $this->processingTrace;
    }


    /**
     * Sets the processing trace
     *
     * @param \Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace $processingTrace
     * @return void
     */
    public function setProcessingTrace(ProcessingTrace $processingTrace): void
    {
        $this->processingTrace = $processingTrace;
    }
}
