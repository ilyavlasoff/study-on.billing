<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Exception\ValueNotFoundException;
use App\Model\Response\OwnedCourseDto;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * Class CourseController
 * @package App\Controller
 * @Route("/api/v1/courses")
 */
class CourseController extends ApiController
{
    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws \Exception
     * @ISGranted("ROLE_SUPER_ADMIN")
     * @Route("/", name="course_create", methods={"POST"})
     * @OA\Post(
     *     tags={"Management"},
     *     @Security(name="Bearer"),
     *     summary="Create a new course",
     *     @OA\RequestBody(
     *          required=true,
     *          description="Edited course fields",
     *          @OA\JsonContent(ref=@Model(type=Course::class, groups={"Default"}))
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="New course successfully created",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="success",
     *                      type="bool"
     *                  ),
     *              ),
     *          )
     *      )
     * )
     */
    public function courseCreate(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $context = DeserializationContext::create()->setGroups(['create']);
        /** @var Course $createdCourse */
        $createdCourse = $serializer->deserialize($request->getContent(), Course::class, 'json', $context);

        if (count($validationErrors = $validator->validate($createdCourse))) {
            throw new ValidationException($validationErrors);
        }

        $entityManager->persist($createdCourse);
        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_CREATED, [], true);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param \App\Repository\CourseRepository $courseRepository
     * @return JsonResponse
     * @Route("/my-courses", name="valid_user_courses", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Courses"},
     *     summary="Get owned courses",
     *     @Security(name="Bearer"),
     *     @OA\Response(
     *          response="200",
     *          description="Course object",
     *          @OA\JsonContent(type="array", @OA\Items(ref=@Model(type=Course::class, groups={"Default"})))
     *     )
     * )
     */
    public function validUserCourseList(
        Request $request,
        SerializerInterface $serializer,
        CourseRepository $courseRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $filteredCourses = $courseRepository->getValidCoursesForUser($user);

        return $this->responseSuccessWithObject($filteredCourses);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     * @Route("/", name="course_list", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Courses"},
     *     summary="Get courses list",
     *     @Security(name="Bearer"),
     *     @OA\Response(
     *          response="200",
     *          description="Owned course list",
     *          @OA\JsonContent(
     *              oneOf={
     *                  @OA\Schema(ref=@Model(type=OwnedCourseDto::class, groups={"Default"})),
     *                  @OA\Schema(ref=@Model(type=Course::class, groups={"Default"}))
     *              }
     *          )
     *     )
     * )
     */
    public function getCourseList(
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var CourseRepository $courseRepository */
        $courseRepository = $entityManager->getRepository(Course::class);

        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $courses = $courseRepository->getCoursesList($user);
        } else {
            $courses = $courseRepository->findAll();
        }

        return $this->responseSuccessWithObject($courses);
    }

    /**
     * @param $code
     * @param EntityManagerInterface $entityManager
     * @param CourseRepository $courseRepository
     * @return JsonResponse
     * @throws \App\Exception\ValueNotFoundException
     * @throws \Doctrine\DBAL\DBALException
     * @Route("/{code}", name="course_item", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Courses"},
     *     summary="Get course by code",
     *     @Security(name="Bearer"),
     *     @OA\Parameter(
     *          in="path",
     *          name="code",
     *          description="Code of course"
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Owned course object",
     *          @OA\JsonContent(
     *              oneOf={
     *                  @OA\Schema(ref=@Model(type=OwnedCourseDto::class, groups={"Default"})),
     *                  @OA\Schema(ref=@Model(type=Course::class, groups={"Default"}))
     *              }
     *          )
     *     ),
     *     @OA\Response(
     *          response="404",
     *          description="Course can not be found"
     *     )
     * )
     */
    public function getCourseItem(
        $code,
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository
    ): JsonResponse {
        /** @var Course $course */
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);

        if (!$course) {
            throw new ValueNotFoundException('Курс с таким кодом не найден');
        }

        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $ownedCourse = new OwnedCourseDto($course);

            /** @var User $user */
            $user = $this->getUser();

            $owning = $courseRepository->getUserOwning($user, $course);

            if ($owning) {
                $ownedCourse->setOwned(true);

                if ($endOwningTime = $owning['valid']) {
                    $ownedCourse->setOwnedUntil(new \DateTime($endOwningTime));
                }
            } else {
                $ownedCourse->setOwned(false);
            }

            return $this->responseSuccessWithObject($ownedCourse);
        }

        return $this->responseSuccessWithObject($course);
    }

    /**
     * @param $code
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param \Symfony\Component\Validator\Validator\ValidatorInterface $validator
     * @return JsonResponse
     * @throws \App\Exception\ValidationException
     * @throws \App\Exception\ValueNotFoundException
     * @Route("/{code}", name="courses_edit", methods={"POST"})
     * @ISGranted("ROLE_SUPER_ADMIN")
     * @OA\Post(
     *     tags={"Management"},
     *     summary="Allow to edit specified course",
     *     @Security(name="Bearer"),
     *     @OA\RequestBody(
     *          required=true,
     *          description="Edited course fields",
     *          @OA\JsonContent(ref=@Model(type=Course::class, groups={"Default"}))
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="New course successfully created",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="success",
     *                      type="bool"
     *                  ),
     *              ),
     *          )
     *      )
     * )
     */
    public function courseEdit(
        $code,
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        $courseRepository = $entityManager->getRepository(Course::class);

        /** @var Course $targetCourse */
        $targetCourse = $courseRepository->findOneBy(['code' => $code]);

        if (!$targetCourse) {
            throw new ValueNotFoundException();
        }

        $context = DeserializationContext::create()->setGroups('edit');
        /** @var Course $editedCourse */
        $editedCourse = $serializer->deserialize($request->getContent(), Course::class, 'json', $context);

        if ($code = $editedCourse->getCode()) {
            $existSameCodes = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);
            if ($existSameCodes) {
                throw new \Exception('This code is already exists');
            }
            $targetCourse->setCode($code);
        }

        if ($type = $editedCourse->getType()) {
            if (count($errors = $validator->validatePropertyValue($targetCourse, 'type', $type)) > 0) {
                throw new ValidationException($errors);
            };
            $targetCourse->setType($type);
        }

        if ($title = $editedCourse->getTitle()) {
            if (count($errors = $validator->validatePropertyValue($targetCourse, 'title', $title)) > 0) {
                throw new ValidationException($errors);
            };
            $targetCourse->setTitle($title);
        }

        if ($price = $editedCourse->getCost()) {
            if (count($errors = $validator->validatePropertyValue($targetCourse, 'cost', $price)) > 0) {
                throw new ValidationException($errors);
            };
            $targetCourse->setCost($price);
        }

        if ($rentTime = $editedCourse->getRentTime()) {
            if (count($errors = $validator->validatePropertyValue($targetCourse, 'rentTime', $rentTime)) > 0) {
                throw new ValidationException($errors);
            };
            $targetCourse->setRentTime($rentTime);
        }

        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_OK, [], true);
    }
}
