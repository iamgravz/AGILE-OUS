<?php
declare(strict_types=1);
namespace Agile;

/**
 * Gmail send request may have reached Google, but receipt wasn't confirmed.
 * Never automatically retry; a human must investigate before any requeue.
 */
final class MailDeliveryUncertain extends \RuntimeException {}
