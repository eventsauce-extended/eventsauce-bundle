<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\OutboundAcl;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

#[OutboundAcl('match_any', 'match_any')]
final class DummyAclMessageDispatcher implements MessageDispatcher
{
    public function dispatch(Message ...$messages): void
    {
    }
}
