<?php
/**
 * Created by PhpStorm.
 * User: Khue Quang Nguyen
 * Date: 21-Aug-17
 * Time: 13:10
 */

namespace MiribotBundle\Model;


use MiribotBundle\Helper\Helper;
use MiribotBundle\Model\Graphmaster\Nodemapper;
use Symfony\Component\HttpKernel\Kernel;

class Graphmaster
{
    protected $kernel;
    protected $graph;
    protected $helper;

    /**
     * Graphmaster constructor.
     * @param Kernel $kernel
     * @param Helper $helper
     */
    public function __construct(Kernel $kernel, Helper $helper)
    {
        $this->kernel = $kernel;
        $this->helper = $helper;
        $this->graph = new Nodemapper('[root]', '[root]', null);
    }

    /**
     * Get graph
     * @return Nodemapper
     */
    public function getGraph()
    {
        return $this->graph;
    }

    /**
     * Build graph
     * @return $this
     */
    public function build()
    {
        // Build AIML path
        $aimlPath = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'aiml';

        // Get all AIML files
        $aimlFiles = glob($aimlPath . DIRECTORY_SEPARATOR . "*.aiml");

        // Fetch AIML data
        $aiml = new \DOMDocument();
        $aiml->loadXML($this->loadAimlData($aimlFiles));

        /** @var \DOMNodeList $categories */
        $categories = $aiml->getElementsByTagName("category");

        // Map AIML data to bot's Graphmaster knowledge
        $this->mapToNodemapper($categories);

        //dump($this->graph);die;

        return $this;
    }

    /**
     * @param $query
     * @return bool|Nodemapper|mixed
     */
    public function matchQueryPattern($query)
    {
        // Find the node that matches query pattern
        $node = $this->match($this->graph, $query);

        if (!$node || !$node->getTemplate()) {
            return false;
        }

        return $node;
    }

    /**
     * Search for reference node
     * @param \DOMElement $srai
     * @return bool|Nodemapper
     */
    public function getReferenceNode($srai, $that, $topic)
    {
        $sraiQuery = $this->helper->string->produceQueries($srai->textContent, $that, $topic);
        return $this->matchQueryPattern($sraiQuery);
    }

    /**
     * @param Nodemapper|bool|mixed $node
     * @param $query
     * @return bool|Nodemapper|mixed
     */
    protected function match($node, $query)
    {
        if (empty($query) && $node->getTemplate() !== null) {
            return $node;
        } else {
            while (!empty($query)) {
                $word = array_shift($query);

                // Check if the word is a reserved keyword
                if (($this->helper->string->stringcmp($word, 'userref') == 0)
                    || ($this->helper->string->stringcmp($word, 'botref') == 0)) {
                    continue;
                }

                $matchingTokens = array("#", "_", $word, "^", "*");
                //print_r(htmlentities($word) . "|" . htmlentities($node->getWord()) . ' --> ' . htmlentities(implode("|", $query)) . "<br/>");

                foreach ($matchingTokens as $token) {
                    $matched = $this->matchToken($node, $token, $query);
                    if ($matched) {
                        return $this->match($matched, $query);
                    }
                }
            }

            if ($node->getTemplate() !== null) {
                return $node;
            }

            return false;
        }
    }

    /**
     * Match a token to node branch
     * @param Nodemapper $node
     * @param $token
     * @param $query
     * @return bool|Nodemapper|mixed
     */
    protected function matchToken(Nodemapper $node, $token, $query)
    {
        /** @var Nodemapper $child */
        foreach($node->getChildren() as $child) {
            // If the current node has <set> tag
            if (strpos($child->getWord(), "<set>") !== FALSE) {
                // Get all words in the set
                $setWords = $this->helper->template->getSetWords(strip_tags($child->getWord()));

                // If the set contains current token then a match is found
                if (in_array(mb_strtolower($token), $setWords)) {
                    // Proceed to next word
                    if ($matchBranch = $this->match($child, $query)) {
                        return $matchBranch;
                    }
                }
            }

            // If the current node contains normal word
            if ($this->helper->string->stringcmp($child->getWord(), $token) == 0) {
                if ($matchBranch = $this->match($child, $query)) {
                    return $matchBranch;
                }
            }
        }

        return false;
    }

    /**
     * Map AIML data to Graphmaster
     * @param $categories
     */
    protected function mapToNodemapper($categories)
    {
        /** @var \DOMElement $category */
        foreach ($categories as $category) {

            // Skip category data in <learn> tags
            if ($category->parentNode && $category->parentNode->tagName == "learn") {
                continue;
            }

            // Get pattern string
            $pattern = $this->extractPatternString($category);

            // Build pattern tokens
            $patternTokens = mb_split("\s", $pattern);
            $patternTokens = array_filter($patternTokens);

            // Create a category branch that contains word Nodemappers lead to a specific template
            // then add to the knowledge Graphmaster
            /** @var Nodemapper $categoryBranchNode */
            $categoryBranchNode = $this->buildCategoryBranch($category, $pattern, array_values($patternTokens));
            $this->graph->addChild($categoryBranchNode);
        }
    }

    /**
     * Extract pattern string from a category
     * @param \DOMElement $category
     * @return string
     */
    protected function extractPatternString(\DOMElement $category)
    {
        $string = "";

        $pattern = $category->getElementsByTagName("pattern")->item(0);

        /** @var \DOMNode $node */
        foreach($pattern->childNodes as $node) {
            $string .= $pattern->ownerDocument->saveXML($node);
        }

        // Add that to the pattern
        /** @var \SimpleXMLElement $that */
        if ($category->getElementsByTagName("that")->length > 0) {
            // In case the category contains that, add it to the pattern
            $string .= " <that> " . $category->getElementsByTagName("that")->item(0)->nodeValue;
        } else {
            $string .= " <that>";
        }

        // Add topic to the pattern
        if ($parent = $category->parentNode) {
            if ($parent->tagName == "topic") {
                $string .= " <topic> " . mb_strtoupper($parent->getAttribute("name"));
            } else {
                $string .= " <topic>";
            }
        } else {
            $string .= " <topic>";
        }

        return $string;
    }

    /**
     * Build a category branch
     * @param \DOMElement $category
     * @param string $pattern
     * @param $patternTokens
     * @return Nodemapper|null
     */
    protected function buildCategoryBranch($category, $pattern, $patternTokens)
    {
        $categoryBranchNode = null;

        for ($i = count($patternTokens) - 1; $i >= 0; $i--) {
            $word = $patternTokens[$i];

            // If the node contains last word in an entry, set response template
            if ($i == count($patternTokens) - 1) {
                $template = $category->getElementsByTagName("template")->item(0);
            } else { // Otherwise leave the template null
                $template = null;
            }

            // Create a new word node
            $wordNode = new Nodemapper($word, $pattern, $template);

            if ($categoryBranchNode) {
                $wordNode->addChild($categoryBranchNode);
            }

            $categoryBranchNode = $wordNode;
        }

        return $categoryBranchNode;
    }

    /**
     * Load AIML data to memory
     * @return string
     */
    protected function loadAimlData($files)
    {
        // Initialize AIML header
        $aimlString = "<?xml version = \"1.0\" encoding = \"UTF-8\"?>\n<aiml version=\"2.0\" encoding=\"UTF-8\">\n";

        // Read and merge all AIML file contents into one raw XML string
        foreach ($files as $aimlFile) {
            $fileContent = strip_tags(file_get_contents($aimlFile), $this->helper->string->getAllowedAIMLTagList());
            $aimlString .= trim($fileContent) . "\n";
        }

        $aimlString .= "</aiml>";

        return $aimlString;
    }

    /**
     * Check if a word is wildcard
     * @param $word
     * @return bool
     */
    protected function isWildcard($word)
    {
        return in_array($word, array("#", "_", "^", "*"));
    }
}
