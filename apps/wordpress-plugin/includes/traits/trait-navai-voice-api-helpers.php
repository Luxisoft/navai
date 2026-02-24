<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-navai-voice-api-helpers-registry.php';
require_once __DIR__ . '/trait-navai-voice-api-helpers-catalog.php';
require_once __DIR__ . '/trait-navai-voice-api-helpers-runtime.php';

trait Navai_Voice_API_Helpers_Trait
{
    use Navai_Voice_API_Helpers_Registry_Trait;
    use Navai_Voice_API_Helpers_Catalog_Trait;
    use Navai_Voice_API_Helpers_Runtime_Trait;
}
