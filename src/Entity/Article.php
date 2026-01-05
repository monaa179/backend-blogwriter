<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 255)]
    private ?string $originalTitle = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $originalDescription = null;

    #[ORM\Column(length: 255)]
    private ?string $suggestedTitle = null;

    #[ORM\Column(length: 255)]
    private ?string $suggestedDescription = null;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, inversedBy: 'articles')]
    private Collection $modules;

    /**
     * @var Collection<int, ArticleVersion>
     */
    #[ORM\OneToMany(targetEntity: ArticleVersion::class, mappedBy: 'article', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $articleVersions;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->articleVersions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getOriginalTitle(): ?string
    {
        return $this->originalTitle;
    }

    public function setOriginalTitle(string $originalTitle): static
    {
        $this->originalTitle = $originalTitle;

        return $this;
    }

    public function getOriginalDescription(): ?string
    {
        return $this->originalDescription;
    }

    public function setOriginalDescription(string $originalDescription): static
    {
        $this->originalDescription = $originalDescription;

        return $this;
    }

    public function getSuggestedTitle(): ?string
    {
        return $this->suggestedTitle;
    }

    public function setSuggestedTitle(string $suggestedTitle): static
    {
        $this->suggestedTitle = $suggestedTitle;

        return $this;
    }

    public function getSuggestedDescription(): ?string
    {
        return $this->suggestedDescription;
    }

    public function setSuggestedDescription(string $suggestedDescription): static
    {
        $this->suggestedDescription = $suggestedDescription;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Module>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(Module $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        $this->modules->removeElement($module);

        return $this;
    }

    /**
     * @return Collection<int, ArticleVersion>
     */
    public function getArticleVersions(): Collection
    {
        return $this->articleVersions;
    }

    public function addArticleVersion(ArticleVersion $articleVersion): static
    {
        if (!$this->articleVersions->contains($articleVersion)) {
            $this->articleVersions->add($articleVersion);
            $articleVersion->setArticle($this);
        }

        return $this;
    }

    public function removeArticleVersion(ArticleVersion $articleVersion): static
    {
        if ($this->articleVersions->removeElement($articleVersion)) {
            // set the owning side to null (unless already changed)
            if ($articleVersion->getArticle() === $this) {
                $articleVersion->setArticle(null);
            }
        }

        return $this;
    }
}
