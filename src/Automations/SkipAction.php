<?php

namespace RadThemes\AlpCrm\Automations;

use RuntimeException;

/**
 * Thrown when an automation action doesn't apply, e.g. the contact has no email address.
 */
class SkipAction extends RuntimeException {}
