<?php

namespace MauticPlugin\PostalBundle\EventSubscriber;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Dsn;

class CallbackSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger, private TransportCallback $transportCallback, private CoreParametersHelper $coreParametersHelper)
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => 'processCallbackRequest',
        ];
    }

    /**
     * Handle bounces & complaints from Amazon.
     */
    public function processCallbackRequest(TransportWebhookEvent $webhookEvent): void
    {
        $dsn = Dsn::fromString($this->coreParametersHelper->get('mailer_dsn'));
        if ('smtp' !== $dsn->getScheme()) {
            return;
        }

        $this->logger->debug('Start processCallbackRequest - webhook from postal server');

        $postData = $webhookEvent->getRequest()->request->all();
        $event    = $postData['event'];
        $payload  = $postData['payload'];
        $message  = isset($payload['original_message']) ? $payload['original_message'] : $payload['message'];
        $email    = $message['to'];
        $emailId  = $message['message_id'];

        if ('MessageDeliveryFailed' == $event) {
            $this->transportCallback->addFailureByAddress($email, 'Delivery failed', DoNotContact::BOUNCED, $emailId);
            $this->logger->debug('Mark email '.$email.' as delivery failed');
        } elseif ('MessageBounced' == $event) {
            $this->transportCallback->addFailureByAddress($email, 'Hard bounce', DoNotContact::BOUNCED, $emailId);
            $this->logger->debug('Mark email '.$email.' as bounced');
        }

        $this->logger->debug('End processCallbackRequest - webhook from postal server');
        $webhookEvent->setResponse(new Response('Callback processed'));
    }
}
