<?php

namespace EnhanceBunnyDnsSync\Sync;

enum SyncExceptionType : string
{
    case ENHANCE_API_EXCEPTION = 'Enhance API exception';
    case BUNNY_API_EXCEPTION = 'Bunny.net API exception';
    case BUNNY_ZONE_DOES_NOT_EXISTS = 'Zone does not exists in Bunny.net';
    case BUNNY_ZONE_ALREADY_EXISTS = 'Zone already exists in Bunny.net';
    case BUNNY_ZONE_NOT_FOUND = 'Zone not found';
    case BUNNY_WRONG_CREDENTIALS = 'Wrong API key for Bunny.net, check twice';
    case BUNNY_GENERIC_ERROR = 'Bunny.net API error';
    case BUNNY_FAILED_TO_CREATE_ZONE = 'Failed to create zone in Bunny.net';
    case WRONG_NAMESERVERS_NUMBER = 'Wrong number of custom nameservers';
    case ZONE_IS_NOT_FINALIZED = 'Zone is not finalized';
}
