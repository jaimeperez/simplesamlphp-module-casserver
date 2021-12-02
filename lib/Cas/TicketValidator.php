<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use SimpleSAML\Configuration;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\CasException;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;

class TicketValidator
{
    /** @var \SimpleSAML\Configuration */
    private Configuration $casconfig;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore */
    private TicketStore $ticketStore;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketFactory */
    private TicketFactory $ticketFactory;

    /**
     * The ticket id doesn't match a ticket
     */
    public const INVALID_TICKET = 'INVALID_TICKET';


    /**
     * TicketValidator constructor.
     * @param \SimpleSAML\Configuration $casconfig
     */
    public function __construct(Configuration $casconfig)
    {
        $this->casconfig = $casconfig;
        $ticketStoreConfig = $casconfig->getValue('ticketstore', ['class' => 'casserver:FileSystemTicketStore']);
        $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        /**
         * @psalm-suppress InvalidStringClass
         * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
         */
        $this->ticketStore = new $ticketStoreClass($casconfig);
        $ticketFactoryClass = Module::resolveClass('casserver:TicketFactory', 'Cas\Ticket');
        /**
         * @psalm-suppress InvalidStringClass
         * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketFactory
         */
        $this->ticketFactory = new $ticketFactoryClass($casconfig);
    }


    /**
     * @param string $ticket the ticket id to load validate
     * @param string $service the service that the ticket was issued to
     * @return string|array|null
     * @throws \SimpleSAML\Module\casserver\Cas\CasException Thrown if ticket doesn't exist, expired, service mismatch
     * @throws \InvalidArgumentException thrown if $ticket or $service parameter is missing
     */
    public function validateAndDeleteTicket(string $ticket, string $service)
    {
        if (empty($ticket)) {
            throw new \InvalidArgumentException('Missing ticket parameter: [ticket]');
        }
        if (empty($service)) {
            throw new \InvalidArgumentException('Missing service parameter: [service]');
        }

        $serviceTicket = $this->ticketStore->getTicket($ticket);
        if ($serviceTicket == null) {
            $message = 'Ticket ' . var_export($ticket, true) . ' not recognized';
            Logger::debug('casserver:' . $message);
            throw new CasException(CasException::INVALID_TICKET, $message);
        }

        // TODO: do proxy vs non proxy ticket check
        $this->ticketStore->deleteTicket($ticket);

        if ($this->ticketFactory->isExpired($serviceTicket)) {
            $message = 'Ticket ' . var_export($ticket, true) . ' has expired';
            Logger::debug('casserver:' . $message);
            throw new CasException(CasException::INVALID_TICKET, $message);
        }

        if (self::sanitize($serviceTicket['service']) !== self::sanitize($service)) {
            $message = 'Mismatching service parameters: expected ' .
                var_export($serviceTicket['service'], true) .
                ' but was: ' . var_export($service, true);

            Logger::debug('casserver:' . $message);
            throw new CasException(CasException::INVALID_SERVICE, $message);
        }


        return $serviceTicket;
    }


    /**
     * Java CAS clients are inconsistent with their sending of jsessionid, so remove it to
     * avoid service url matching issues.
     * @param string $parameter The service url to sanitize
     * @return string The sanitized url
     */
    public static function sanitize(string $parameter): string
    {
        return preg_replace(
            '/;jsessionid=.*[^?].*$/U',
            '',
            preg_replace('/;jsessionid=.*[?]/U', '?', urldecode($parameter))
        );
    }
}
