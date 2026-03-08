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

namespace Box\Mod\Massmailer;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use FOSSBilling\Environment;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected ?MassmailerMessageRepository $messageRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->messageRepository = $this->di['em']->getRepository(MassmailerMessage::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getMessageRepository(): MassmailerMessageRepository
    {
        if ($this->messageRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->messageRepository = $this->di['em']->getRepository(MassmailerMessage::class);
        }

        return $this->messageRepository;
    }

    public function install(): void
    {
        $extensionService = $this->di['mod_service']('extension');

        $sql = '
        CREATE TABLE IF NOT EXISTS `mod_massmailer` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `from_email` varchar(255) DEFAULT NULL,
        `from_name` varchar(255) DEFAULT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `content` text DEFAULT NULL,
        `filter` text DEFAULT NULL,
        `status` varchar(255) DEFAULT NULL,
        `sent_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ';
        $this->di['db']->exec($sql);

        // default config values
        $extensionService->setConfig(['ext' => 'mod_massmailer', 'limit' => '2', 'interval' => '10', 'test_client_id' => 1]);
    }

    /**
     * Get filtered list of client IDs that should receive a mass mail message.
     *
     * @return array List of associative arrays with 'id' keys
     */
    public function getMessageReceivers(MassmailerMessage $message, array $data = []): array
    {
        $filter = $message->getFilterDecoded();

        $sql = 'SELECT DISTINCT c.id
            FROM client c
            LEFT JOIN client_order co ON (co.client_id = c.id)
            WHERE 1
        ';

        $values = [];
        if (!empty($filter)) {
            if (isset($filter['client_status']) && !empty($filter['client_status'])) {
                $sql .= sprintf(" AND c.status IN ('%s')", implode("', '", $filter['client_status']));
            }

            if (isset($filter['client_groups']) && !empty($filter['client_groups'])) {
                $sql .= sprintf(" AND c.client_group_id IN ('%s')", implode("', '", $filter['client_groups']));
            }

            if (isset($filter['has_order']) && !empty($filter['has_order'])) {
                $sql .= sprintf(" AND co.product_id IN ('%s')", implode("', '", $filter['has_order']));
            }

            if (isset($filter['has_order_with_status']) && !empty($filter['has_order_with_status'])) {
                $sql .= sprintf(" AND co.status IN ('%s')", implode("', '", $filter['has_order_with_status']));
            }
        }

        $sql .= ' ORDER BY c.id DESC';

        return $this->di['db']->getAll($sql, $values);
    }

    /**
     * Parse the subject and content of a mass mail message for a specific client.
     *
     * @return array [parsed_subject, parsed_content]
     */
    public function getParsed(MassmailerMessage $message, int $client_id): array
    {
        $clientService = $this->di['mod_service']('client');
        $systemService = $this->di['mod_service']('system');

        $client = $clientService->get(['id' => $client_id]);
        $clientArr = $clientService->toApiArray($client, true, null);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $message->getSubject();
        $ps = $systemService->renderString($vars['_tpl'], false, $vars);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $message->getContent();
        $pc = $systemService->renderString($vars['_tpl'], false, $vars);

        return [$ps, $pc];
    }

    /**
     * Send a mass mail message to a specific client.
     */
    public function sendMessage(MassmailerMessage $message, int $client_id, bool $sendNow = false): bool
    {
        [$ps, $pc] = $this->getParsed($message, $client_id);

        $clientService = $this->di['mod_service']('client');

        $client = $clientService->get(['id' => $client_id]);

        $data = [
            'to' => $client->email,
            'to_name' => $client->first_name . ' ' . $client->last_name,
            'from' => $message->getFromEmail(),
            'from_name' => $message->getFromName(),
            'subject' => $ps,
            'content' => $pc,
            'client_id' => $client_id,
        ];

        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            throw new \FOSSBilling\InformationException('Disabled for security reasons (Demo mode enabled)');
        }

        if (!Environment::isProduction()) {
            if (DEBUG) {
                error_log('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());
            }

            return true;
        }

        $emailService = $this->di['mod_service']('email');
        $emailService->sendMail($data['to'], $data['from'], $data['subject'], $data['content'], $data['to_name'], $data['from_name'], $data['client_id'], null, $sendNow);

        return true;
    }

    /**
     * Send a mail message by params (used by cron/queue).
     */
    public function sendMail(array $params): void
    {
        $message = $this->getMessageRepository()->find($params['msg_id']);
        if (!$message instanceof MassmailerMessage) {
            throw new \Exception('Mass mail message not found');
        }
        $this->sendMessage($message, $params['client_id']);
    }
}
