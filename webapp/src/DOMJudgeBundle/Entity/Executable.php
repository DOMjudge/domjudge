<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles
 * @ORM\Entity()
 * @ORM\Table(name="executable", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Executable
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="execid", length=32, options={"comment"="Unique ID (string)"}, nullable=false)
     */
    private $execid;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum", length=32, options={"comment"="Md5sum of zip file"}, nullable=true)
     */
    private $md5sum;

    /**
     * @var string
     * @ORM\Column(type="blob", name="zipfile", options={"comment"="Zip file"}, nullable=false)
     */
    private $zipfile;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255, options={"comment"="Description of this executable"}, nullable=true)
     */
    private $description;

    /**
     * @var string
     * @ORM\Column(type="string", name="type", length=32, options={"comment"="Type of executable"}, nullable=false)
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
     * @param string $zipfile
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
     * @return string
     */
    public function getZipfile()
    {
        return $this->zipfile;
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
     * @param \DOMJudgeBundle\Entity\Language $language
     *
     * @return Executable
     */
    public function addLanguage(\DOMJudgeBundle\Entity\Language $language)
    {
        $this->languages[] = $language;

        return $this;
    }

    /**
     * Remove language
     *
     * @param \DOMJudgeBundle\Entity\Language $language
     */
    public function removeLanguage(\DOMJudgeBundle\Entity\Language $language)
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
     * @param \DOMJudgeBundle\Entity\Problem $problemsCompare
     *
     * @return Executable
     */
    public function addProblemsCompare(\DOMJudgeBundle\Entity\Problem $problemsCompare)
    {
        $this->problems_compare[] = $problemsCompare;

        return $this;
    }

    /**
     * Remove problemsCompare
     *
     * @param \DOMJudgeBundle\Entity\Problem $problemsCompare
     */
    public function removeProblemsCompare(\DOMJudgeBundle\Entity\Problem $problemsCompare)
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
     * @param \DOMJudgeBundle\Entity\Problem $problemsRun
     *
     * @return Executable
     */
    public function addProblemsRun(\DOMJudgeBundle\Entity\Problem $problemsRun)
    {
        $this->problems_run[] = $problemsRun;

        return $this;
    }

    /**
     * Remove problemsRun
     *
     * @param \DOMJudgeBundle\Entity\Problem $problemsRun
     */
    public function removeProblemsRun(\DOMJudgeBundle\Entity\Problem $problemsRun)
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
