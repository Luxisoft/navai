<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-navai-voice-settings-internals-values.php';
require_once __DIR__ . '/trait-navai-voice-settings-internals-navigation.php';
require_once __DIR__ . '/trait-navai-voice-settings-internals-custom.php';
require_once __DIR__ . '/trait-navai-voice-settings-internals-routes.php';

trait Navai_Voice_Settings_Internals_Trait
{
    use Navai_Voice_Settings_Internals_Values_Trait;
    use Navai_Voice_Settings_Internals_Navigation_Trait;
    use Navai_Voice_Settings_Internals_Custom_Trait;
    use Navai_Voice_Settings_Internals_Routes_Trait;
}
