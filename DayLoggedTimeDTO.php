<?php
class DayLoggedTimeDTO
{
    /**
     * @var integer
     */
    private $hours;

    /**
     * @var string
     */
    private $comment;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @return int
     */
    public function getHours(): int
    {
        return $this->hours;
    }

    /**
     * @param int $hours
     * @return DayLoggedTimeDTO
     */
    public function setHours(int $hours): DayLoggedTimeDTO
    {
        $this->hours = $hours;

        return $this;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     * @return DayLoggedTimeDTO
     */
    public function setComment(string $comment): DayLoggedTimeDTO
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    /**
     * @param DateTime $dateTime
     * @return DayLoggedTimeDTO
     */
    public function setDateTime(DateTime $dateTime): DayLoggedTimeDTO
    {
        $this->dateTime = $dateTime;

        return $this;
    }
}