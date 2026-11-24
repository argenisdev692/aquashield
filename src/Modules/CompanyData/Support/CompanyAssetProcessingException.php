<?php

declare(strict_types=1);

namespace Modules\CompanyData\Support;

use RuntimeException;

/**
 * A file passed the upload rules but could not be decoded/re-encoded.
 *
 * Distinguishes "bad image" (422) from "storage provider failed" (500).
 */
final class CompanyAssetProcessingException extends RuntimeException {}
