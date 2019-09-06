<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Programming languages in which teams can submit solutions
 * @ORM\Entity()
 * @ORM\Table(
 *     name="language",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Programming languages in which teams can submit solutions"},
 *     indexes={@ORM\Index(name="compile_script", columns={"compile_script"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {"190"}}),
 *     })
 * @UniqueEntity("langid")
 * @UniqueEntity("externalid")
 */
class Language extends BaseApiEntity
{

    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="langid", length=32, options={"comment"="Language ID (string)"}, nullable=false)
     * @Serializer\Exclude()
     * @Assert\NotBlank()
     * @Assert\NotEqualTo("add")
     * @Identifier()
     */
    protected $langid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255, nullable=true,
     *     options={"default"="NULL","comment"="Language ID to expose in the REST API"})
     * @Serializer\SerializedName("id")
     * @Serializer\Groups({"Default", "Nonstrict"})
     */
    protected $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive language name"}, nullable=false)
     * @Serializer\Groups({"Default", "Nonstrict"})
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var string[]
     * @ORM\Column(type="json", length=4294967295, name="extensions",
     *     options={"comment"="List of recognized extensions (JSON encoded)","default":"NULL"},
     *     nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Type("array<string>")
     * @Assert\NotBlank()
     */
    private $extensions;

    /**
     * @var bool
     * @ORM\Column(type="boolean", name="filter_compiler_files",
     *     options={"comment"="Whether to filter the files passed to the compiler by the extension list.",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $filterCompilerFiles = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_submit",
     *     options={"comment"="Are submissions accepted in this language?","default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $allowSubmit = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_judge",
     *     options={"comment"="Are submissions in this language judged?","default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $allowJudge = true;

    /**
     * @var double
     * @ORM\Column(type="float", name="time_factor",
     *     options={"comment"="Language-specific factor multiplied by problem run times","default"="1"},
     *     nullable=false)
     * @Serializer\Type("double")
     * @Serializer\Groups({"Nonstrict"})
     * @Assert\GreaterThan(0)
     * @Assert\NotBlank()
     */
    private $timeFactor = 1;

    /**
     * @var string
     * @ORM\Column(type="string", name="compile_script", length=32,
     *     options={"comment"="Script to compile source code for this language","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $compile_script;

    /**
     * @var bool
     * @ORM\Column(type="boolean", name="require_entry_point",
     *     options={"comment"="Whether submissions require a code entry point to be specified.","default":"0"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $require_entry_point = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="entry_point_description",
     *     options={"comment"="The description used in the UI for the entry point field.","default"="NULL"},
     *     nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $entry_point_description;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="languages")
     * @ORM\JoinColumn(name="compile_script", referencedColumnName="execid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $compile_executable;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="language")
     * @Serializer\Exclude()
     */
    private $submissions;

    /**
     * Set langid
     *
     * @param string $langid
     *
     * @return Language
     */
    public function setLangid($langid)
    {
        $this->langid = $langid;

        return $this;
    }

    /**
     * Get langid
     *
     * @return string
     */
    public function getLangid()
    {
        return $this->langid;
    }

    /**
     * Set externalid
     *
     * @param string externalid
     *
     * @return Language
     */
    public function setExternalid(string $externalid)
    {
        $this->externalid = $externalid;

        return $this;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalid()
    {
        return $this->externalid;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Language
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set extensions
     *
     * @param string[] $extensions
     *
     * @return Language
     */
    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    /**
     * Get extensions
     *
     * @return string[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Set filterCompilerFiles
     *
     * @param bool $filterCompilerFiles
     *
     * @return Language
     */
    public function setFilterCompilerFiles(bool $filterCompilerFiles)
    {
        $this->filterCompilerFiles = $filterCompilerFiles;

        return $this;
    }

    /**
     * Get filterCompilerFiles
     *
     * @return bool
     */
    public function getFilterCompilerFiles()
    {
        return $this->filterCompilerFiles;
    }

    /**
     * Set allowSubmit
     *
     * @param boolean $allowSubmit
     *
     * @return Language
     */
    public function setAllowSubmit($allowSubmit)
    {
        $this->allowSubmit = $allowSubmit;

        return $this;
    }

    /**
     * Get allowSubmit
     *
     * @return boolean
     */
    public function getAllowSubmit()
    {
        return $this->allowSubmit;
    }

    /**
     * Set allowJudge
     *
     * @param boolean $allowJudge
     *
     * @return Language
     */
    public function setAllowJudge($allowJudge)
    {
        $this->allowJudge = $allowJudge;

        return $this;
    }

    /**
     * Get allowJudge
     *
     * @return boolean
     */
    public function getAllowJudge()
    {
        return $this->allowJudge;
    }

    /**
     * Set timeFactor
     *
     * @param float $timeFactor
     *
     * @return Language
     */
    public function setTimeFactor($timeFactor)
    {
        $this->timeFactor = $timeFactor;

        return $this;
    }

    /**
     * Get timeFactor
     *
     * @return float
     */
    public function getTimeFactor()
    {
        return $this->timeFactor;
    }

    /**
     * Set compileScript
     *
     * @param string $compileScript
     *
     * @return Language
     */
    public function setCompileScript($compileScript)
    {
        $this->compile_script = $compileScript;

        return $this;
    }

    /**
     * Get compileScript
     *
     * @return string
     */
    public function getCompileScript()
    {
        return $this->compile_script;
    }

    /**
     * Set requireEntryPoint
     *
     * @param boolean $requireEntryPoint
     *
     * @return Language
     */
    public function setRequireEntryPoint($requireEntryPoint)
    {
        $this->require_entry_point = $requireEntryPoint;

        return $this;
    }

    /**
     * Get requireEntryPoint
     *
     * @return boolean
     */
    public function getRequireEntryPoint()
    {
        return $this->require_entry_point;
    }

    /**
     * Set entryPointDescription
     *
     * @param string $entryPointDescription
     *
     * @return Language
     */
    public function setEntryPointDescription($entryPointDescription)
    {
        $this->entry_point_description = $entryPointDescription;

        return $this;
    }

    /**
     * Get entryPointDescription
     *
     * @return string
     */
    public function getEntryPointDescription()
    {
        return $this->entry_point_description;
    }

    /**
     * Set compileExecutable
     *
     * @param \App\Entity\Executable $compileExecutable
     *
     * @return Language
     */
    public function setCompileExecutable(\App\Entity\Executable $compileExecutable = null)
    {
        $this->compile_executable = $compileExecutable;

        return $this;
    }

    /**
     * Get compileExecutable
     *
     * @return \App\Entity\Executable
     */
    public function getCompileExecutable()
    {
        return $this->compile_executable;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add submission
     *
     * @param \App\Entity\Submission $submission
     *
     * @return Language
     */
    public function addSubmission(\App\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \App\Entity\Submission $submission
     */
    public function removeSubmission(\App\Entity\Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }

    /**
     * Get the language for the ACE editor for this langauge
     * @return string
     */
    public function getAceLanguage()
    {
        switch ($this->getLangid()) {
            case 'c':
            case 'cpp':
            case 'cxx':
                return 'c_cpp';
            case 'pas':
                return 'pascal';
            case 'hs':
                return 'haskell';
            case 'pl':
                return 'perl';
            case 'bash':
                return 'sh';
            case 'py2':
            case 'py3':
                return 'python';
            case 'adb':
                return 'ada';
            case 'plg':
                return 'prolog';
            case 'rb':
                return 'ruby';
        }
        return $this->getLangid();
    }
}
