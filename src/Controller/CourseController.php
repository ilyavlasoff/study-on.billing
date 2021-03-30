<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class CourseController
 * @package App\Controller
 * @Route("/api/v1")
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
     * @Route("/courses", name="course_create", methods={"POST"})
     */
    public function courseCreate(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var Course $createdCourse */
        $createdCourse = $serializer->deserialize($request->getContent(), Course::class, 'json');

        if (count($validationErrors = $validator->validate($createdCourse))) {
            throw new \Exception();
        }

        $entityManager->persist($createdCourse);
        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_CREATED);
    }

    /**
     * @param $code
     * @param Response $response
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws \Exception
     * @Route("/courses/{code}", name="courses_edit", methods={"POST"})
     */
    public function courseEdit(
        $code,
        Response $response,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        $courseRepository = $entityManager->getRepository(Course::class);

        /** @var Course $targetCourse */
        $targetCourse = $courseRepository->findOneBy(['code' => $code]);

        if (!$targetCourse) {
            throw new \Exception();
        }
        /** @var Course $editedCourse */
        $editedCourse = $serializer->deserialize(
            $response->getContent(),
            Course::class,
            DeserializationContext::create()->setGroups('change')
        );

        if ($type = $editedCourse->getType()) {
            $targetCourse->setType($type);
        }

        if ($title = $editedCourse->getTitle()) {
            $targetCourse->setTitle($title);
        }

        if ($code = $editedCourse->getCode()) {
            if ($courseRepository->findOneBy(['code' => $code])) {
                throw new \Exception();
            }
            $targetCourse->setCode($code);
        }

        if ($price = $editedCourse->getCost()) {
            $targetCourse->setCost($price);
        }

        $entityManager->flush();

        return new JsonResponse(json_encode(['success' => 'true']), Response::HTTP_OK);
    }
}
