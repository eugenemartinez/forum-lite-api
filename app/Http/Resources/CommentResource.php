<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CommentResource",
 *     title="Comment Resource",
 *     description="Comment resource representation",
 *     @OA\Property(property="id", type="integer", format="int64", description="Comment ID", example=1),
 *     @OA\Property(property="content", type="string", description="Comment content", example="This is a great post!"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of comment creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last comment update"),
 *     @OA\Property(property="user", ref="#/components/schemas/UserResource", description="The user who created the comment"),
 *     @OA\Property(
 *         property="post",
 *         type="object",
 *         description="Basic information about the post this comment belongs to",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="title", type="string", example="My First Post")
 *     )
 * )
 */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'post' => $this->whenLoaded('post', function () {
                return [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                ];
            }),
        ];
    }
}
