<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Model\CoursePaymentDto;
use App\Model\TransactionHistoryDto;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CourseController
 * @package App\Controller
 * @Route("/api/v1")
 */
class PaymentController extends ApiController
{
    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @Route("/courses", name="course_list", methods={"GET"})
     */
    public function courseList(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $allCourses = $entityManager->getRepository(Course::class)->findAll();

        return new JsonResponse($serializer->serialize(
            $allCourses,
            'json',
            SerializationContext::create()->setInitialType('array<App\Entity\Course>')
        ), Response::HTTP_OK, [], true);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param TransactionRepository $transactionRepository
     * @return JsonResponse
     * @Route("/my-courses", name="valid_user_courses", methods={"GET"})
     */
    public function validUserCourseList(
        Request $request,
        SerializerInterface $serializer,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw new UnauthorizedHttpException('User was not found');
        }

        $filteredCourses = $transactionRepository->getValidCoursesForUser($user);

        return new JsonResponse($serializer->serialize(
            $filteredCourses,
            'json',
            SerializationContext::create()->setInitialType('array<App\Entity\Course>')
        ), Response::HTTP_OK, [], true);
    }

    /**
     * @param $id
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws EntityNotFoundException
     * @Route("/courses/{code}", name="course_item", methods={"GET"})
     */
    public function course($code, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);

        if (!$course) {
            throw new EntityNotFoundException('Course not found');
        }

        return $this->serializedResponse($course, $serializer, Response::HTTP_OK);
    }

    /**
     * @param $code
     * @param EntityManagerInterface $entityManager
     * @param PaymentService $paymentService
     * @return JsonResponse
     * @throws EntityNotFoundException
     * @Route("/courses/{code}/pay", name="course_pay", methods={"POST"})
     */
    public function coursePayment(
        $code,
        EntityManagerInterface $entityManager,
        PaymentService $paymentService,
        SerializerInterface $serializer
    ): JsonResponse {
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);

        if (!$course) {
            throw new EntityNotFoundException('Course not found');
        }

        try {
            $paidTransaction = $paymentService->coursePayment($this->getUser(), $course);
        } catch (\Exception $e) {
            throw $e;
        }

        $coursePaymentDto = new CoursePaymentDto($paidTransaction);

        return new JsonResponse(
            $serializer->serialize($coursePaymentDto, 'json'),
            Response::HTTP_OK,
            [],
            true
        );
    }

    /**
     * @param Request $request
     * @param TransactionRepository $transactionRepository
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @Route("/transactions", name="transactions_history", methods={"GET"})
     */
    public function transactionHistory(
        Request $request,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $type = $request->query->get('type');

        if ($type) {
            $typedTransaction = new Transaction();
            $typedTransaction->setStringOperationType($type);
        } else {
            $typedTransaction = null;
        }

        $courseCode = $request->query->get('course_code');

        if ($courseCode) {
            /** @var Course $requestCourse */
            $requestCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        } else {
            $requestCourse = null;
        }

        $skipExpired = $request->query->get('skip_expired');

        $filteredTransactions = $transactionRepository->filterUserTransactions(
            $user,
            $typedTransaction,
            $requestCourse,
            $skipExpired
        );

        $transactionsDto = [];
        foreach ($filteredTransactions as $filteredTransaction) {
            $transactionsDto[] = new TransactionHistoryDto($filteredTransaction);
        }

        return new JsonResponse($serializer->serialize(
            $transactionsDto,
            'json',
            SerializationContext::create()->setInitialType('array<App\Model\TransactionHistoryDto>')
        ), Response::HTTP_OK, [], true);
    }
}
