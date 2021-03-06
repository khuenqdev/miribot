<?php
/**
 * Created by PhpStorm.
 * User: Khue Quang Nguyen
 * Date: 20-Aug-17
 * Time: 11:13
 */

namespace MiribotBundle\Model;

use ChrisKonnertz\StringCalc\StringCalc;
use MiribotBundle\Helper\Helper;
use MiribotBundle\Model\Graphmaster\Nodemapper;
use Twig\Node\Node;

class Brain
{
    /**
     * @var Graphmaster
     */
    protected $knowledge;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * Brain constructor.
     * @param Graphmaster $graph
     * @param Helper $helper
     */
    public function __construct(Graphmaster $graph, Helper $helper)
    {
        $this->knowledge = $graph;
        $this->helper = $helper;
        $this->init();
    }

    /**
     * Initialize bot's brain
     */
    protected function init()
    {
        // Build bot's knowledge
        $this->knowledge->build();
    }

    /**
     * Get answer from bot's brain
     * @param $userInput
     * @return array
     */
    public function getAnswer($userInput)
    {

        // Check user input for conversation topic and load corresponding topic from the AIML data set
        $this->determineTopic($userInput);

        // Load topic data
        $this->knowledge->loadTopicData($this->helper->memory->recallTopic());

        // Pre-process user input to break it into sentences
        $sentences = $this->helper->string->sentenceSplitting($userInput);

        // Think for answer
        $response = $this->thinkForAnswer($sentences);

        if ($mathResult = $this->helper->evaluateMathInString($userInput)) {
            $response['answer'] = $mathResult . "! " . $response['answer'];
        }

        // Save bot's last sentence
        $this->helper->memory->rememberLastSentence($response['answer']);

        // Save chat log
        if ($userInput != "*") { // Do not save bluffings
            $this->helper->saveToChatLog($userInput, $response['answer']);
        }

        return $response;
    }

    /**
     * Determine whether the user is talking about certain known topic
     * @param $userInput
     */
    protected function determineTopic($userInput)
    {
        $userInput = $this->helper->string->substituteWords($userInput);
        $topicList = $this->helper->memory->recallUserData('topic_list');

        foreach ($topicList as $topic) {
            if (mb_ereg_match(".*\b({$topic})\b", $userInput)) {
                $this->helper->memory->rememberTopic($topic);
            }
        }
    }

    /**
     * Think for an answer
     * @param $sentences
     * @return array
     */
    protected function thinkForAnswer($sentences)
    {

        $answer = "";
        $emotion = false;

        // The sentences serve as query string for the brain to get its answer
        foreach ($sentences as $sentence) {

            // Get last sentence of the bot as <that>
            $that = $this->helper->memory->recallLastSentence();

            // Get <topic> of the bot
            $topic = $this->helper->memory->recallTopic();

            // Produce a query for the bot
            $query = $this->helper->string->produceQueries($sentence, $that, $topic);

            // Think for an answer and get a match answer template
            $knowledge = $this->queryKnowledge($query, $sentence, $that, $topic);

            if (!$knowledge) {
                continue;
            }

            $matchedAnswerTemplate = $knowledge['answer'];
            $emotion = $knowledge['emotion'];

            // Combine all answer templates to get final answers
            if (!empty($matchedAnswerTemplate)) {
                if (!empty($answer)) {
                    $lastChar = substr($answer, -1);
                    if (ctype_alnum($lastChar)) {
                        $answer .= "."; // Add a period to the answer before moving on
                    }
                }

                if (strpos($answer, $matchedAnswerTemplate) === FALSE) {
                    $answer .= " " . $matchedAnswerTemplate;
                }
            }
        }

        if (empty($answer)) {
            $answer = "...";
        }

        $allowedEmo = $this->getAllowedEmotions();
        if (!$emotion || !in_array($emotion, $allowedEmo)) {
            $idx = array_rand($allowedEmo);
            $emotion = $allowedEmo[$idx];
        }

        return array(
            'answer' => ucfirst(trim($answer)),
            'emotion' => $emotion
        );
    }

    /**
     * Query the knowledge
     * @param array $query A set of query tokens
     * @param string $queryString Original query string
     * @param string $that
     * @param string $topic
     * @return array|bool
     */
    protected function queryKnowledge($query, $queryString, $that, $topic)
    {
        // Find a word node that has template matches the query pattern
        $node = $this->knowledge->matchQueryPattern($query);

        // Return blank answer if we cannot find the node
        if (!$node) {
            return false;
        }

        $tokenizedInput = $this->helper->string->tokenize($queryString);
        $node = $this->produceResponse($node, $tokenizedInput, $that, $topic);
        $template = $node->getTemplate();
        $answer = strip_tags($template->ownerDocument->saveHTML($template), $this->helper->string->getAllowedHTMLTags());

        return array(
            'answer' => trim($answer),
            'emotion' => $node->getExtraData("emotion")
        );
    }

    /**
     * Produce final response node
     * @param Nodemapper $node
     * @param $tokenizedInput
     * @param $that
     * @param $topic
     * @return Nodemapper
     */
    protected function produceResponse(Nodemapper $node, $tokenizedInput, $that, $topic)
    {
        $referenceNodes = array();

        // Collect srai reference nodes
        if ($node->getTemplate()->getElementsByTagName("srai")->length > 0) {
            $srais = $node->getTemplate()->getElementsByTagName("srai");
            $noOfSrais = $srais->length;
            for ($i = 0; $i < $noOfSrais; $i++) {
                $srai = $srais->item(0);
                $this->helper->template->handleWildcards($srai, $node, $tokenizedInput);
                $referenceNode = $this->knowledge->getReferenceNode($srai, $that, $topic);
                if ($referenceNode) {
                    $tokenizedSrai = $this->helper->string->tokenize($srai->textContent);
                    $referenceNodes[] = $this->produceResponse($referenceNode, $tokenizedSrai, $that, $topic);
                }
            }
        }

        // Process the node template to get final response
        $this->helper->template->processNodeTemplate($node, $referenceNodes, $tokenizedInput);

        return $node;
    }

    /**
     * Get a list of allowed emotions
     * @return array
     */
    protected function getAllowedEmotions()
    {
        return array(
            "angry",
            "cute",
            "default",
            "doubtful",
            "happy",
            "joyful",
            "neutral",
            "nope",
            "sad",
            "scared",
            "surprise",
            "thoughtful",
            "serious",
            "searchful",
            "shy"
        );
    }
}
