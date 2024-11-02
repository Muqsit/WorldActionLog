<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class EventListenerAttribute{

	public function __construct(
		readonly public string $action_id
	){}
}