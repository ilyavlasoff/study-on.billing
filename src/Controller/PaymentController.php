<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\ValueNotFoundException;
use App\Model\Response\CoursePaymentDto;
use App\Model\Response\TransactionHistoryDto;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CourseController
 *
 * @Route("/api/v1")
 */
class PaymentController extends ApiController
{
    public function __construct(SerializerInterface $serializer)
    {
        parent::__construct($serializer);
    }

    /**
     * @param $code
     * @param EntityManagerInterface $entityManager
     * @param PaymentService $paymentService
     * @param \JMS\Serializer\SerializerInterface $serializer
     *
     * @return JsonResponse
     *
     * @throws \App\Exception\CashNotEnoughException
     * @throws \App\Exception\ValueNotFoundException
     * @Route("/courses/{code}/pay", name="course_pay", methods={"POST"})
     *
     * @OA\Post(
     *     tags={"Payments"},
     *     summary="Pay method",
     *     @Security(name="Bearer"),
     *     @OA\Parameter(
     *         in="path",
     *         required=true,
     *         name="code",
     *         description="Code of course"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful payment report",
     *         @OA\JsonContent(ref=@Model(type=CoursePaymentDto::class, groups={"Default"}))
     *     )
     * )
     */
    public function coursePayment(
        $code,
        EntityManagerInterface $entityManager,
        PaymentService $paymentService,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var Course $course */
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code, 'active' => true]);

        if (!$course) {
            throw new ValueNotFoundException();
        }

        $paidTransaction = $paymentService->coursePayment($this->getUser(), $course);

        $coursePaymentDto = new CoursePaymentDto($paidTransaction);

        return $this->responseSuccessWithObject($coursePaymentDto);
    }

    /**
     * @param Request $request
     * @param TransactionRepository $transactionRepository
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     *
     * @return JsonResponse
     * @Route("/transactions", name="transactions_history", methods={"GET"})
     *
     * @throws \Doctrine\ORM\EntityNotFoundException
     *
     * @OA\Get(
     *     tags={"Payments"},
     *     summary="Retreive transactions history of specified user",
     *     @Security(name="Bearer"),
     *     @OA\Parameter(
     *          in="query",
     *          required=false,
     *          name="filter",
     *          description="Specifiy array of entity filtering params",
     *          @OA\Items(type="array", @OA\Items(type="string"))
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Array of transactions",
     *          @OA\JsonContent(type="array",
     *              @OA\Items(ref=@Model(type=TransactionHistoryDto::class, groups={"Default"})))
     *     )
     * )
     */
    public function transactionHistory(
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        /** @var array $filter */
        $filter = $request->query->get('filter');

        $typedTransaction = null;
        $requestCourse = null;
        $skipExpired = null;

        if ($filter) {
            if (!is_array($filter)) {
                throw new \Exception('Unexpected argument');
            }

            if (array_key_exists('type', $filter)) {
                $typeFilter = $filter['type'];
                $typedTransaction = new Transaction();
                $typedTransaction->setStringOperationType($typeFilter);
            }

            if (array_key_exists('course_code', $filter)) {
                $filterCourseCode = $filter['course_code'];
                /** @var Course $requestCourse */
                $requestCourse = $entityManager->getRepository(Course::class)->findOneBy(
                    ['code' => $filterCourseCode, 'active' => true]
                );
                if (!$requestCourse) {
                    throw new ValueNotFoundException();
                }
            }

            if (array_key_exists('skip_expired', $filter)) {
                $skipExpired = (bool) ($filter['skip_expired']);
            }
        }

        $filteredTransactions = $transactionRepository->filterUserTransactions(
            $user,
            $typedTransaction,
            $requestCourse,
            $skipExpired
        );

        $transactionsDtoList = [];
        foreach ($filteredTransactions as $filteredTransaction) {
            $transactionsDtoList[] = new TransactionHistoryDto($filteredTransaction);
        }

        return $this->responseSuccessWithObject($transactionsDtoList);
    }
}
