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

namespace Box\Mod\Massmailer\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Massmailer\Repository\MassmailerMessageRepository::class)]
#[ORM\Table(name: 'mod_massmailer')]
#[ORM\HasLifecycleCallbacks]
class MassmailerMessage implements ApiArrayInterface, TimestampInterface
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'from_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(name: 'from_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $filter = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $sentAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    public function toApiArray(): array
    {
        return [
            'id' => $this->getId(),
            'from_email' => $this->getFromEmail(),
            'from_name' => $this->getFromName(),
            'subject' => $this->getSubject(),
            'content' => $this->getContent(),
            'filter' => $this->getFilter() ?? [],
            'status' => $this->getStatus(),
            'sent_at' => $this->getSentAt()?->format('Y-m-d H:i:s'),
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // --- Getters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // --- Setters ---

    public function setFromEmail(?string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setFilter(?array $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    public function setStatus(?string $status): self
    {
        $allowedStatuses = [self::STATUS_DRAFT, self::STATUS_SENT];

        if ($status !== null && !in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status "%s". Allowed values: %s', $status, implode(', ', $allowedStatuses)));
        }

        $this->status = $status;

        return $this;
    }

    public function setSentAt(?\DateTime $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
