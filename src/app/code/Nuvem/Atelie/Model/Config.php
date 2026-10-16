<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

/**
 * Paths of every configuration field of the atelie.
 *
 * system.xml (F4.5) and config.xml defaults (F0.5) must use these constants,
 * so a path change happens here and nowhere else.
 */
class Config
{
    public const XML_PATH_ENGRAVING_PRICE = 'nuvem_atelie/engraving/price';

    public const XML_PATH_ENGRAVING_LIMIT = 'nuvem_atelie/engraving/limit';

    public const XML_PATH_ENGRAVING_FORBIDDEN_WORDS = 'nuvem_atelie/engraving/forbidden_words';

    public const XML_PATH_STOCK_LAST_UNITS = 'nuvem_atelie/stock/last_units';

    public const XML_PATH_FACTORY_EMAIL = 'nuvem_atelie/factory/email';
}
