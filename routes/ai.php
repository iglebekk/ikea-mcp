<?php

use App\Mcp\Servers\IkeaServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('ikea', IkeaServer::class);

Mcp::web('/mcp/ikea', IkeaServer::class)
    ->middleware(['throttle:60,1']);
