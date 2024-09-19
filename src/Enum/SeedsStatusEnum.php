<?php

namespace WeCanSync\MultiTenancyBundle\Enum;

enum SeedsStatusEnum: string
{
    case SEEDS_CREATED = 'SEEDS_CREATED';
    case SEEDS_NOT_CREATED = 'SEEDS_NOT_CREATED';
}
