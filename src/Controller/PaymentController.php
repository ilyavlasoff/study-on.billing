<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Model\CoursePaymentDto;
use App\Model\OwnedCourseDto;
use App\Model\TransactionHistoryDto;
use App\Repository\CourseRepository;
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
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository
    ): JsonResponse {
        $foundedCourseItems = $entityManager->getRepository(Course::class)->findAll();

        return new JsonResponse($serializer->serialize(
            $foundedCourseItems,
            'json',
            SerializationContext::create()->setInitialType('array<App\Model\Course>')
        ), Response::HTTP_OK, [], true);
    }

    /**
     * @param SerializerInterface $serializer
     * @param CourseRepository $courseRepository
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     * @Route("/labeled-courses", name="labeled_courses", methods={"GET"})
     */
    public function coursesWithOwningLabels(SerializerInterface $serializer, CourseRepository $courseRepository)
    {
        $user = $this->getUser();

        $courses = $courseRepository->getCoursesList($user);

        return new JsonResponse($serializer->serialize(
            $courses,
            'json',
            SerializationContext::create()->setInitialType('array<App\Model\OwnedCourseDto>')
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
     * @param $code
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param CourseRepository $courseRepository
     * @return JsonResponse
     * @throws EntityNotFoundException
     * @throws \Doctrine\DBAL\DBALException
     * @Route("/courses/{code}", name="course_item", methods={"GET"})
     */
    public function courseItem(
        $code,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        CourseRepository $courseRepository
    ): JsonResponse {
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);

        if (!$course) {
            throw new EntityNotFoundException('Course not found');
        }

        $ownedCourse = new OwnedCourseDto($course);
        var_dump(gettype($this->getUser()));

        if (($user = $this->getUser()) instanceof User) {
            $owning = $courseRepository->getUserOwning($user, $course);

            if ($owning) {
                $ownedCourse->setOwned(true);

                if ($endOwningTime = $owning['valid']) {
                    $ownedCourse->setOwnedUntil($endOwningTime);
                }
            } else {
                $ownedCourse->setOwned(false);
            }
        }

        return $this->serializedResponse($ownedCourse, $serializer, Response::HTTP_OK);
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
