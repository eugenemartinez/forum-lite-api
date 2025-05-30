<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PostResource",
 *     title="Post Resource",
 *     description="Post resource representation",
 *     @OA\Property(property="id", type="integer", format="int64", description="Post ID", example=1),
 *     @OA\Property(property="title", type="string", description="Post title", example="My First Post"),
 *     @OA\Property(property="content", type="string", description="Post content", example="This is the content of my first post."),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of post creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last post update"),
 *     @OA\Property(property="user", ref="#/components/schemas/UserResource", description="The user who created the post"),
 *     @OA\Property(property="comments_count", type="integer", description="Number of comments on the post", example=5, nullable=true)
 * )
 */
class PostResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'comments_count' => $this->whenCounted('comments'), // This will be present if loaded with loadCount('comments')
        ];
    }
}
