<?php
declare(strict_types=1);

namespace App\Http\Controllers\MailChimp;

use App\Database\Entities\MailChimp\MailChimpList;
use App\Database\Entities\MailChimp\MailChimpListMember;
use App\Http\Controllers\Controller;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mailchimp\Mailchimp;

class ListMembersController extends Controller
{
    /**
     * @var \Mailchimp\Mailchimp
     */
    private $mailChimp;

    /**
     * ListsController constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Mailchimp\Mailchimp $mailchimp
     */
    public function __construct(EntityManagerInterface $entityManager, Mailchimp $mailchimp)
    {
        parent::__construct($entityManager);

        $this->mailChimp = $mailchimp;
    }

    /**
     * Create MailChimp member.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request, string $listId): JsonResponse
    {
        // Get the list
        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if ($list === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        // Instantiate entity
        $listMember = new MailChimpListMember($request->all());
        // Validate entity
        $validator = $this->getValidationFactory()->make($list->toMailChimpArray(), $list->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Save list member into db
            $this->saveEntity($listMember);
            // Save list member into MailChimp
            $response = $this->mailChimp->post(sprintf('lists/%s/members', $list->getMailChimpId()), $list->toMailChimpArray());
            // Set MailChimp id on the list and save list into db
            $this->saveEntity($list->setMailChimpId($response->get('id')));
        } catch (Exception $exception) {
            // Return error response if something goes wrong
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Remove member from MailChimp list.
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(string $listId, string $memberId): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if ($list === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        /** @var \App\Database\Entities\MailChimp\MailChimpListMember|null $list */
        $listMember = $this->entityManager->getRepository(MailChimpListMember::class)->find($memberId);

        if ($listMember === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpListMember[%s] not found', $memberId)],
                404
            );
        }

        try {
            // Remove list member from database
            $this->removeEntity($listMember);
            // Remove list from MailChimp
            $this->mailChimp->delete(\sprintf('lists/%s/members/%s', $list->getMailChimpId(), $listMember->getMailChimpId()));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse([]);
    }

    /**
     * Retrieve and return MailChimp list member.
     *
     * @param string $listId
     * @param string $memberId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $listId, string $memberId): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if ($list === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        /** @var \App\Database\Entities\MailChimp\MailChimpListMember|null $list */
        $listMember = $this->entityManager->getRepository(MailChimpListMember::class)->find($memberId);

        if ($listMember === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpListMember[%s] not found', $memberId)],
                404
            );
        }

        return $this->successfulResponse($listMember->toArray());
    }

    /**
     * Update MailChimp list member.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $listId
     * @param string $memberId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $listId, string $memberId): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if ($list === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        /** @var \App\Database\Entities\MailChimp\MailChimpListMember|null $list */
        $listMember = $this->entityManager->getRepository(MailChimpListMember::class)->find($memberId);

        if ($listMember === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpListMember[%s] not found', $memberId)],
                404
            );
        }

        // Update list properties
        $listMember->fill($request->all());

        // Validate entity
        $validator = $this->getValidationFactory()->make($list->toMailChimpArray(), $list->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Update list into database
            $this->saveEntity($listMember);
            // Update list into MailChimp
            $this->mailChimp->patch(\sprintf('lists/%s/members/%s', $list->getMailChimpId(), $listMember->getMailChimpId()), $listMember->toMailChimpArray());
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($listMember->toArray());
    }
}
