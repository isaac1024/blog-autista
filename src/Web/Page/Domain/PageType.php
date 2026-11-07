<?php

declare(strict_types=1);

namespace Web\Page\Domain;

/**
 * Kind of page, as the CMS publishes it. The site has each unique page once,
 * such as the home page, which is served at its root.
 */
enum PageType: string
{
    case Home = 'home';
    case Contact = 'contact';
    case Search = 'search';
    case LegalNotice = 'legal_notice';
    case PrivacyPolicy = 'privacy_policy';
    case CookiePolicy = 'cookie_policy';
    case Standard = 'standard';
}
