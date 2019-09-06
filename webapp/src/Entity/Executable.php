<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compile, compare, and run script executable bundles
 * @ORM\Entity()
 * @ORM\Table(
 *     name="executable",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Compile, compare, and run script executable bundles"})
 */
class Executable
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="execid", length=32,
     *     options={"comment"="Executable ID (string)"}, nullable=false)
     * @Assert\NotBlank()
     * @Identifier()
     */
    private $execid;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum", length=32,
     *     options={"comment"="Md5sum of zip file","default"="NULL","fixed"=true},
     *     nullable=true)
     */
    private $md5sum;

    /**
     * @var resource|string
     * @ORM\Column(type="blob", name="zipfile",
     *     options={"comment"="Zip file","default"="NULL"}, nullable=true)
     */
    private $zipfile;

    private $zipfile_as_string = null;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255,
     *     options={"comment"="Description of this executable","default"="NULL"},
     *     nullable=true)
     * @Assert\NotBlank()
     */
    private $description;

    /**
     * @var string
     * @ORM\Column(type="string", name="type", length=32,
     *     options={"comment"="Type of executable"}, nullable=false)
     * @Assert\Choice({"compare", "compile", "run"})
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="Language", mappedBy="compile_executable")
     */
    private $languages;

    /**
     * @ORM\OneToMany(targetEntity="Problem", mappedBy="compare_executable")
     */
    private $problems_compare;

    /**
     * @ORM\OneToMany(targetEntity="Problem", mappedBy="run_executable")
     */
    private $problems_run;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->languages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->problems_compare = new ArrayCollection();
        $this->problems_run = new ArrayCollection();
    }

    /**
     * Set execid
     *
     * @param string $execid
     *
     * @return Executable
     */
    public function setExecid($execid)
    {
        $this->execid = $execid;

        return $this;
    }

    /**
     * Get execid
     *
     * @return string
     */
    public function getExecid()
    {
        return $this->execid;
    }

    /**
     * Set md5sum
     *
     * @param string $md5sum
     *
     * @return Executable
     */
    public function setMd5sum($md5sum)
    {
        $this->md5sum = $md5sum;

        return $this;
    }

    /**
     * Get md5sum
     *
     * @return string
     */
    public function getMd5sum()
    {
        return $this->md5sum;
    }

    /**
     * Set zipfile
     *
     * @param resource|string $zipfile
     *
     * @return Executable
     */
    public function setZipfile($zipfile)
    {
        $this->zipfile = $zipfile;

        return $this;
    }

    /**
     * Get zipfile
     *
     * @param bool $asString
     * @return resource|string
     */
    public function getZipfile(bool $asString = false)
    {
        if ($asString && $this->zipfile !== null) {
            if ($this->zipfile_as_string === null) {
                $this->zipfile_as_string = stream_get_contents($this->zipfile);
            }
            return $this->zipfile_as_string;
        }
        return $this->zipfile;
    }

    /**
     * Get the length of the zipfile
     * @return int
     */
    public function getZipFileSize()
    {
        return strlen(stream_get_contents($this->getZipfile()));
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Executable
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Executable
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Add language
     *
     * @param \App\Entity\Language $language
     *
     * @return Executable
     */
    public function addLanguage(\App\Entity\Language $language)
    {
        $this->languages[] = $language;

        return $this;
    }

    /**
     * Remove language
     *
     * @param \App\Entity\Language $language
     */
    public function removeLanguage(\App\Entity\Language $language)
    {
        $this->languages->removeElement($language);
    }

    /**
     * Get languages
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * Add problemsCompare
     *
     * @param \App\Entity\Problem $problemsCompare
     *
     * @return Executable
     */
    public function addProblemsCompare(\App\Entity\Problem $problemsCompare)
    {
        $this->problems_compare[] = $problemsCompare;

        return $this;
    }

    /**
     * Remove problemsCompare
     *
     * @param \App\Entity\Problem $problemsCompare
     */
    public function removeProblemsCompare(\App\Entity\Problem $problemsCompare)
    {
        $this->problems_compare->removeElement($problemsCompare);
    }

    /**
     * Get problemsCompare
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProblemsCompare()
    {
        return $this->problems_compare;
    }

    /**
     * Add problemsRun
     *
     * @param \App\Entity\Problem $problemsRun
     *
     * @return Executable
     */
    public function addProblemsRun(\App\Entity\Problem $problemsRun)
    {
        $this->problems_run[] = $problemsRun;

        return $this;
    }

    /**
     * Remove problemsRun
     *
     * @param \App\Entity\Problem $problemsRun
     */
    public function removeProblemsRun(\App\Entity\Problem $problemsRun)
    {
        $this->problems_run->removeElement($problemsRun);
    }

    /**
     * Get problemsRun
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProblemsRun()
    {
        return $this->problems_run;
    }
}
