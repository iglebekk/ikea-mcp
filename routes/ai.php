<?php

use App\Mcp\Servers\IkeaServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('ikea', IkeaServer::class);

// Register the OAuth 2.1 discovery and dynamic client registration routes.
Mcp::oauthRoutes();

// The web server is protected by OAuth via Laravel Passport (auth:api guard),
// so the authenticated user drives which IKEA market their calls query.
Mcp::web('/mcp/ikea', IkeaServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);
