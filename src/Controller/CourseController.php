<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Exception\ValueNotFoundException;
use App\Model\Response\OwnedCourseDto;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $createdCourse->setActive(true);

        if (count($validationErrors = $validator->validate($createdCourse))) {
            throw new ValidationException($validationErrors);
        }

        $entityManager->persist($createdCourse);
        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_CREATED, [], true);
    }

    /**
     * @param \App\Repository\CourseRepository $courseRepository
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
     *          @OA\JsonContent(@OA\Schema(ref=@Model(type=OwnedCourseDto::class, groups={"Default"})))
     *     )
     * )
     */
    public function getCourseList(CourseRepository $courseRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $courses = $courseRepository->getCoursesList($user);

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
     *          required=true,
     *          description="Code of course"
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Owned course object",
     *          @OA\JsonContent(@OA\Schema(ref=@Model(type=OwnedCourseDto::class, groups={"Default"})))
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
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $code, 'active' => true]);

        if (!$course) {
            throw new ValueNotFoundException();
        }

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
     *     summary="Edit fields of specified course",
     *     @Security(name="Bearer"),
     *     @OA\Parameter(
     *          in="path",
     *          name="code",
     *          required=true,
     *          description="Code of course"
     *     ),
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
        $targetCourse = $courseRepository->findOneBy(['code' => $code, 'active' => true]);

        if (!$targetCourse) {
            throw new ValueNotFoundException();
        }

        $context = DeserializationContext::create()->setGroups('edit');
        /** @var Course $editedCourse */
        $editedCourse = $serializer->deserialize($request->getContent(), Course::class, 'json', $context);

        $targetCourse->setCode($editedCourse->getCode());
        $targetCourse->setType($editedCourse->getType());
        $targetCourse->setTitle($editedCourse->getTitle());
        $targetCourse->setCost($editedCourse->getCost());
        $targetCourse->setRentTime($editedCourse->getRentTime());

        $errors = $validator->validate($targetCourse);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_OK, [], true);
    }


    /**
     * @param $code
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Route("/{code}", name="courses_delete", methods={"DELETE"})
     * @ISGranted("ROLE_SUPER_ADMIN")
     *
     * @OA\Delete (
     *     tags={"Management"},
     *     summary="Delete specified course",
     *     @Security(name="Bearer"),
     *     @OA\Parameter(
     *          required=true,
     *          in="path",
     *          name="code",
     *          description="Code of course"
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
    public function deleteCourse($code, EntityManagerInterface $entityManager): JsonResponse
    {
        $courseRepository = $entityManager->getRepository(Course::class);

        /** @var Course $targetCourse */
        $targetCourse = $courseRepository->findOneBy(['code' => $code, 'active' => true]);

        if ($targetCourse) {
            $targetCourse->setActive(false);
            $entityManager->flush();
        }

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_OK, [], true);
    }
}
