<?php
declare(strict_types=1);

namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DocumentUriPathFinder
{
    /**
     * @var Connection
     */
    private $dbal;

    /**
     * @var ?ContentStreamIdentifier
     */
    private $liveContentStreamIdentifierRuntimeCache;

    public function injectEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->dbal = $entityManager->getConnection();
    }

    public function findNodeAddressForRequestPathAndDimensionSpacePoint(string $requestPath, DimensionSpacePoint $dimensionSpacePoint): ?NodeAddress
    {
        $nodeAggregateIdentifier = $this->dbal->fetchColumn('SELECT nodeAggregateIdentifier FROM document_uri WHERE dimensionSpacepointHash = :dimensionSpacepointHash AND uriPath = :uriPath', [
            'dimensionSpacepointHash' => $dimensionSpacePoint->getHash(),
            'uriPath' => $requestPath,
        ]);
        if ($nodeAggregateIdentifier === false) {
            return null;
        }
        return new NodeAddress(
            $this->getLiveContentStreamIdentifier(),
            $dimensionSpacePoint,
            NodeAggregateIdentifier::fromString($nodeAggregateIdentifier),
            WorkspaceName::forLive()
        );
    }


    public function findUriPathForNodeAddress(NodeAddress $nodeAddress): ?string
    {
        $uriPath = $this->dbal->fetchColumn('SELECT uriPath FROM document_uri WHERE dimensionSpacepointHash = :dimensionSpacepointHash AND nodeAggregateIdentifier = :nodeAggregateIdentifier', [
            'dimensionSpacepointHash' => $nodeAddress->getDimensionSpacePoint()->getHash(),
            'nodeAggregateIdentifier' => $nodeAddress->getNodeAggregateIdentifier(),
        ]);
        if ($uriPath === false) {
            return null;
        }
        return $uriPath;
    }

    private function getLiveContentStreamIdentifier(): ContentStreamIdentifier
    {
        if ($this->liveContentStreamIdentifierRuntimeCache === null) {
            $this->liveContentStreamIdentifierRuntimeCache = ContentStreamIdentifier::fromString($this->dbal->fetchColumn('SELECT contentStreamIdentifier FROM document_uri_livecontentstreams'));
        }
        return $this->liveContentStreamIdentifierRuntimeCache;
    }

}
