<?php

namespace App\Services\Brain;

/**
 * A Brain task used its token limit: no further AI call is made for it (TaskTokenBudget).
 * The message is the user-facing explanation.
 */
class TaskTokenLimitExceeded extends \RuntimeException
{
}
