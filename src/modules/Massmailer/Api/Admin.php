<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Api;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of active mail messages.
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     */
    public function get_list(array $data): array
    {
        $repo = $this->getService()->getMessageRepository();
        $qb = $repo->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($qb);
    }

    /**
     * Get mail message by id.
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function get(array $data): array
    {
        $message = $this->_getMessage($data);

        return $message->toApiArray();
    }

    /**
     * Update mail message.
     *
     * @optional string $subject - mail message title
     * @optional string $content - mail message content
     * @optional string $status - mail message status
     * @optional string $from_name - mail message email from name
     * @optional string $from_email - mail message email from email
     * @optional array $filter  - filter parameters to select clients
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function update(array $data): bool
    {
        $message = $this->_getMessage($data);

        if (isset($data['content'])) {
            $message->setContent($data['content']);
        }

        if (isset($data['subject'])) {
            $message->setSubject($data['subject']);
        }

        if (isset($data['status'])) {
            $message->setStatus($data['status']);
        }

        if (isset($data['filter'])) {
            $message->setFilter($data['filter']);
        }

        if (isset($data['from_name'])) {
            if (empty($data['from_name'])) {
                throw new \FOSSBilling\InformationException('Message from name cannot be empty');
            }
            $message->setFromName($data['from_name']);
        }

        if (isset($data['from_email'])) {
            $this->di['tools']->validateAndSanitizeEmail($data['from_email']);
            $message->setFromEmail($data['from_email']);
        }

        $this->di['em']->persist($message);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated mail message #%s', $message->getId());

        return true;
    }

    /**
     * Create mail message.
     *
     * @optional string $content - mail message content
     *
     * @return int New message ID
     */
    #[RequiredParams(['subject' => 'Message subject was not passed'])]
    public function create(array $data): int
    {
        $default_content = '{% apply markdown %}
Hi {{ c.first_name }} {{ c.last_name }},

Your email is: {{ c.email }}

Aenean vut sagittis in natoque tortor. Facilisis magnis duis nec eros! Augue
sed quis tortor porttitor? Rhoncus tortor pid et a enim dis adipiscing eros
facilisis nunc. Phasellus dis odio lacus pulvinar vel lundium dapibus turpis.

Urna parturient, ultricies nascetur? Et a. Elementum in dapibus ut vel ut
magna tempor, dapibus lacus sed? Ut velit dignissim placerat, tristique pid
vut amet et nunc! Elementum dolor, dictumst porta ultrices. Rhoncus, amet.

Order our services at {{ "order"|link }}

{{ guest.system_company.name }} - {{ guest.system_company.signature }}
{% endapply %}
        ';
        $systemService = $this->di['mod_service']('system');
        $company = $systemService->getCompany();

        $message = new MassmailerMessage();
        $message->setFromEmail($company['email'])
            ->setFromName($company['name'])
            ->setSubject($data['subject'])
            ->setContent($data['content'] ?? $default_content)
            ->setStatus(MassmailerMessage::STATUS_DRAFT);

        $this->di['em']->persist($message);
        $this->di['em']->flush();

        $this->di['logger']->info('Created mail message #%s', $message->getId());

        return $message->getId();
    }

    /**
     * Send test mail message by ID to client.
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function send_test(array $data): bool
    {
        $message = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();

        if (empty($message->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $this->getService()->sendMessage($message, $client_id, true);

        $this->di['logger']->info('Sent test mail message #%s to client ', $message->getId());

        return true;
    }

    /**
     * Send mail message by ID.
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function send(array $data): bool
    {
        $message = $this->_getMessage($data);

        if (empty($message->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $clients = $this->getService()->getMessageReceivers($message, $data);
        foreach ($clients as $c) {
            $this->getService()->sendMessage($message, $c['id']);
        }

        $message->setStatus(MassmailerMessage::STATUS_SENT)
            ->setSentAt(new \DateTime());

        $this->di['em']->persist($message);
        $this->di['em']->flush();

        $this->di['logger']->info('Added mass mail messages #%s to queue', $message->getId());

        return true;
    }

    /**
     * Copy mail message by ID.
     *
     * @return int New message ID
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function copy(array $data): int
    {
        $message = $this->_getMessage($data);

        $copy = new MassmailerMessage();
        $copy->setFromEmail($message->getFromEmail())
            ->setFromName($message->getFromName())
            ->setSubject($message->getSubject() . ' (Copy)')
            ->setContent($message->getContent())
            ->setFilter($message->getFilter())
            ->setStatus(MassmailerMessage::STATUS_DRAFT);

        $this->di['em']->persist($copy);
        $this->di['em']->flush();

        $this->di['logger']->info('Copied mail message #%s to #%s', $message->getId(), $copy->getId());

        return $copy->getId();
    }

    /**
     * Get message receivers list.
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function receivers(array $data): array
    {
        $message = $this->_getMessage($data);

        return $this->getService()->getMessageReceivers($message, $data);
    }

    /**
     * Delete mail message by ID.
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function delete(array $data): bool
    {
        $message = $this->_getMessage($data);
        $id = $message->getId();

        $this->di['em']->remove($message);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed mail message #%s', $id);

        return true;
    }

    /**
     * Generate preview text.
     *
     * @return array - parsed subject and content strings
     */
    #[RequiredParams(['id' => 'Message ID was not passed'])]
    public function preview(array $data): array
    {
        $message = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        [$ps, $pc] = $this->getService()->getParsed($message, $client_id);

        $recipients = [];
        $getRecipients = $data['include_recipients'] ?? false;
        $clients = $this->getService()->getMessageReceivers($message, $data);

        if ($getRecipients) {
            $clientService = $this->di['mod_service']('client');
            foreach ($clients as $client) {
                $clientInfo = $clientService->get(['id' => $client['id']]);
                $recipients[] = [
                    'email' => $clientInfo->email,
                    'name' => $clientInfo->first_name . ' ' . $clientInfo->last_name,
                ];
            }
        }

        return [
            'subject' => $ps,
            'content' => $pc,
            'recipients' => $recipients,
        ];
    }

    /**
     * Returns the email associated with the test client.
     */
    public function get_test_client(): string
    {
        try {
            $client = $this->di['mod_service']('client')->get(['id' => $this->_getTestClientId()]);
        } catch (\Exception) {
            return 'Unknown';
        }

        return $client->email;
    }

    private function _getTestClientId(): int
    {
        $mod = $this->di['mod']('massmailer');
        $c = $mod->getConfig();

        $required = [
            'test_client_id' => 'Client ID needs to be configured in mass mailer settings.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        return (int) $c['test_client_id'];
    }

    #[RequiredParams(['id' => 'Message ID was not passed'])]
    private function _getMessage(array $data): MassmailerMessage
    {
        $repo = $this->getService()->getMessageRepository();
        $message = $repo->find($data['id']);

        if (!$message instanceof MassmailerMessage) {
            throw new \FOSSBilling\Exception('Message not found');
        }

        return $message;
    }
}
