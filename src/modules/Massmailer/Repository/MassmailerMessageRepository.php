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

namespace Box\Mod\Massmailer\Repository;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EntityRepository<MassmailerMessage>
 */
class MassmailerMessageRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for searching mass mailer messages with optional filters.
     *
     * @param array $data array of filters: 'status', 'search'
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m');

        if (!empty($data['status'])) {
            $qb->andWhere('m.status = :status')
               ->setParameter('status', $data['status']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('m.subject LIKE :search OR m.content LIKE :search OR m.fromEmail LIKE :search OR m.fromName LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('m.createdAt', 'DESC');

        return $qb;
    }
}
