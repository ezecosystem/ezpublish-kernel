<?php
/**
 * File containing the ContentType controller class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Server\Controller;
use eZ\Publish\Core\REST\Common\UrlHandler;
use eZ\Publish\Core\REST\Common\Message;
use eZ\Publish\Core\REST\Common\Input;
use eZ\Publish\Core\REST\Common\Exceptions;
use eZ\Publish\Core\REST\Server\Exceptions\ForbiddenException;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;

use eZ\Publish\Core\REST\Server\Values;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupCreateStruct;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupUpdateStruct;

use Qafoo\RMF;

/**
 * ContentType controller
 */
class ContentType
{
    /**
     * Input dispatcher
     *
     * @var \eZ\Publish\Core\REST\Common\Input\Dispatcher
     */
    protected $inputDispatcher;

    /**
     * URL handler
     *
     * @var \eZ\Publish\Core\REST\Common\UrlHandler
     */
    protected $urlHandler;

    /**
     * Content type service
     *
     * @var \eZ\Publish\API\Repository\ContentTypeService
     */
    protected $contentTypeService;

    /**
     * Construct controller
     *
     * @param \eZ\Publish\Core\REST\Common\Input\Dispatcher $inputDispatcher
     * @param \eZ\Publish\Core\REST\Common\UrlHandler $urlHandler
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     */
    public function __construct( Input\Dispatcher $inputDispatcher, UrlHandler $urlHandler, ContentTypeService $contentTypeService )
    {
        $this->inputDispatcher = $inputDispatcher;
        $this->urlHandler = $urlHandler;
        $this->contentTypeService = $contentTypeService;
    }

    /**
     * Creates a new content type group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\CreatedContentTypeGroup
     */
    public function createContentTypeGroup( RMF\Request $request )
    {
        $createStruct = $this->inputDispatcher->parse(
            new Message(
                array( 'Content-Type' => $request->contentType ),
                $request->body
            )
        );

        try
        {
            return new Values\CreatedContentTypeGroup(
                array(
                    'contentTypeGroup' => $this->contentTypeService->createContentTypeGroup( $createStruct )
                )
            );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new ForbiddenException( $e->getMessage() );
        }
    }

    /**
     * Updates a content type group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     */
    public function updateContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        $createStruct = $this->inputDispatcher->parse(
            new Message(
                array( 'Content-Type' => $request->contentType ),
                $request->body
            )
        );

        try
        {
            $this->contentTypeService->updateContentTypeGroup(
                $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] ),
                $this->mapToGroupUpdateStruct( $createStruct )
            );

            return $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new ForbiddenException( $e->getMessage() );
        }
    }

    /**
     * The given content type group is deleted
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ResourceDeleted
     */
    public function deleteContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        $contentTypeGroup = $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );

        $contentTypes = $this->contentTypeService->loadContentTypes( $contentTypeGroup );
        if ( !empty( $contentTypes ) )
        {
            throw new ForbiddenException( 'Only empty content type groups can be deleted' );
        }

        $this->contentTypeService->deleteContentTypeGroup( $contentTypeGroup );

        return new Values\ResourceDeleted();
    }

    /**
     * Returns a list of all content type groups
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ContentTypeGroupList
     */
    public function loadContentTypeGroupList( RMF\Request $request )
    {
        return new Values\ContentTypeGroupList(
            $this->contentTypeService->loadContentTypeGroups()
        );
    }

    /**
     * Returns the content type group given by id
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     */
    public function loadContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        return $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );
    }

    /**
     * Load a content info by remote ID
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentType
     */
    public function loadContentType( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'type', $request->path );

        return $this->contentTypeService->loadContentType( $urlValues['type'] );
    }

    /**
     * Loads FieldDefinitions for a given type
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\FieldDefinitionList
     */
    public function loadFieldDefinitionList( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinitions', $request->path );

        $contentType = $this->contentTypeService->loadContentType( $urlValues['type'] );

        return new Values\FieldDefinitionList(
            $contentType,
            $contentType->getFieldDefinitions()
        );
    }

    /**
     * Loads a single FieldDefinition
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\RestFieldDefinition
     */
    public function loadFieldDefinition( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinition', $request->path );

        $contentType = $this->contentTypeService->loadContentType( $urlValues['type'] );

        foreach ( $contentType->getFieldDefinitions() as $fieldDefinition )
        {
            if ( $fieldDefinition->id == $urlValues['fieldDefinition'] )
            {
                return new Values\RestFieldDefinition(
                    $contentType,
                    $fieldDefinition
                );
            }
        }

        throw new Exceptions\NotFoundException( "Field definition not found: '{$request->path}'." );
    }

    /**
     * Converts the provided ContentTypeGroupCreateStruct to ContentTypeGroupUpdateStruct
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupCreateStruct $createStruct
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupUpdateStruct
     */
    private function mapToGroupUpdateStruct( ContentTypeGroupCreateStruct $createStruct )
    {
        return new ContentTypeGroupUpdateStruct(
            array(
                'identifier' => $createStruct->identifier,
                'modifierId' => $createStruct->creatorId,
                'modificationDate' => $createStruct->creationDate,
                'mainLanguageCode' => $createStruct->mainLanguageCode,
                'names' => $createStruct->names,
                'descriptions' => $createStruct->descriptions
            )
        );
    }
}
