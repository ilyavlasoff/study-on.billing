<?php

namespace App\Controller;

use App\Exception\ValueNotFoundException;
use App\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;

/**
 * Class UnauthorizedAccessController
 * @package App\Controller
 * @Route("/api/v1/u")
 */
class UnauthorizedAccessController extends ApiController
{
    /**
     * @param \App\Repository\CourseRepository $courseRepository
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @Route("/courses", name="ua_courses_list", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Anonymous access"},
     *     summary="Get courses list",
     *     @OA\Response(
     *          response="200",
     *          description="Owned course list",
     *          @OA\JsonContent(@OA\Schema(ref=@Model(type=Course::class, groups={"Default"})))
     *     )
     * )
     */
    public function getCoursesList(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findBy(['active' => true]);

        return $this->responseSuccessWithObject($courses);
    }

    /**
     * @param $code
     * @param \App\Repository\CourseRepository $courseRepository
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \App\Exception\ValueNotFoundException
     * @Route("/courses/{code}", name="ua_course", methods={"GET"})
     *
     * @OA\Get(
     *     tags={"Anonymous access"},
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
     *          @OA\JsonContent(@OA\Schema(ref=@Model(type=Course::class, groups={"Default"})))
     *     ),
     *     @OA\Response(
     *          response="404",
     *          description="Course can not be found"
     *     )
     * )
     */
    public function getCourseItem($code, CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['code' => $code, 'active' => true]);

        if (!$course) {
            throw new ValueNotFoundException();
        }

        return $this->responseSuccessWithObject($course);
    }
}
