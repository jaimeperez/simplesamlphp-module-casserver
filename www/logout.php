<?php

/*
 *    simpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
 *
 *    Copyright (C) 2013  Bjorn R. Jensen
 *
 *    This library is free software; you can redistribute it and/or
 *    modify it under the terms of the GNU Lesser General Public
 *    License as published by the Free Software Foundation; either
 *    version 2.1 of the License, or (at your option) any later version.
 *
 *    This library is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *    Lesser General Public License for more details.
 *
 *    You should have received a copy of the GNU Lesser General Public
 *    License along with this library; if not, write to the Free Software
 *    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * Incoming parameters:
 *  url     - optional if a logout page is displayed
 */

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = \SimpleSAML\Configuration::getConfig('module_casserver.php');

if (!$casconfig->getValue('enable_logout', false)) {
    $message = 'Logout not allowed';

    \SimpleSAML\Logger::debug('casserver:' . $message);

    throw new \Exception($message);
}

$skipLogoutPage = $casconfig->getValue('skip_logout_page', false);

if ($skipLogoutPage && !array_key_exists('url', $_GET)) {
    $message = 'Required URL query parameter [url] not provided. (CAS Server)';

    \SimpleSAML\Logger::debug('casserver:' . $message);

    throw new \Exception($message);
}
/* Load simpleSAMLphp metadata */

$as = new \SimpleSAML\Auth\Simple($casconfig->getValue('authsource'));

$session = \SimpleSAML\Session::getSession();

if (!is_null($session)) {
    $ticketStoreConfig = $casconfig->getValue('ticketstore', ['class' => 'casserver:FileSystemTicketStore']);
    $ticketStoreClass = \SimpleSAML\Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    /** @psalm-suppress InvalidStringClass */
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketStore->deleteTicket($session->getSessionId());
}

if ($as->isAuthenticated()) {
    \SimpleSAML\Logger::debug('casserver: performing a real logout');

    if ($casconfig->getValue('skip_logout_page', false)) {
        $as->logout($_GET['url']);
    } else {
        $as->logout(
            \SimpleSAML\Utils\HTTP::addURLParameters(
                \SimpleSAML\Module::getModuleURL('casserver/loggedOut.php'),
                array_key_exists('url', $_GET) ? ['url' => $_GET['url']] : []
            )
        );
    }
} else {
    \SimpleSAML\Logger::debug('casserver: no session to log out of, performing redirect');

    if ($casconfig->getValue('skip_logout_page', false)) {
        \SimpleSAML\Utils\HTTP::redirectTrustedURL(SimpleSAML\Utils\HTTP::addURLParameters($_GET['url'], []));
    } else {
        \SimpleSAML\Utils\HTTP::redirectTrustedURL(
            \SimpleSAML\Utils\HTTP::addURLParameters(
                \SimpleSAML\Module::getModuleURL('casserver/loggedOut.php'),
                array_key_exists('url', $_GET) ? ['url' => $_GET['url']] : []
            )
        );
    }
}
