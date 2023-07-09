<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Public blog posts sent by the jury
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="blog_post",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Public blog posts sent by the jury"},
 *     indexes={
 *         @ORM\Index(name="slug", columns={"slug"}),
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="slug", columns={"slug"})
 *     })
 * @UniqueEntity("slug")
 */
class BlogPost extends BaseApiEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="blogpostid",
     *     options={"comment"="Blog post ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected int $blogpostid;

    /**
     * @ORM\Column(type="string", name="slug", length=255,
     *     options={"comment"="Unique slug"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private string $slug;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="publishtime", options={"comment"="Time sent", "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $publishtime;

    /**
     * @ORM\Column(type="string", name="author", length=255,
     *     options={"comment"="Name of the post author"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $author;

    /**
     * @ORM\Column(type="string", name="title", length=511,
     *     options={"comment"="Blog post title"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private string $title;

    /**
     * @ORM\Column(type="text", length=4294967295, name="subtitle",
     *     options={"comment"="Blog post subtitle"},
     *     nullable=false)
     * @Serializer\SerializedName("subtitle")
     */
    private string $subtitle;

    /**
     * @ORM\Column(type="string", name="thumbnail_file_name", length=255,
     *     options={"comment"="Thumbnail file name"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private ?string $thumbnail_file_name;

    /**
     * @ORM\Column(type="text", length=4294967295, name="body",
     *     options={"comment"="Blog post text"},
     *     nullable=false)
     * @Serializer\SerializedName("text")
     */
    private string $body;

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setBlogpostid(int $blogpostid): BlogPost
    {
        $this->blogpostid = $blogpostid;
        return $this;
    }

    public function getBlogpostid(): int
    {
        return $this->blogpostid;
    }

    /** @param string|float $publishtime */
    public function setPublishtime($publishtime): BlogPost
    {
        $this->publishtime = $publishtime;
        return $this;
    }

    /** @return string|float */
    public function getPublishtime()
    {
        return $this->publishtime;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getThumbnailFileName(): string
    {
        return $this->thumbnail_file_name;
    }

    public function setThumbnailFileName(string $thumbnailFileName): void
    {
        $this->thumbnail_file_name = $thumbnailFileName;
    }

    public function setAuthor(?string $juryMember): BlogPost
    {
        $this->author = $juryMember;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setBody(string $body): BlogPost
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
