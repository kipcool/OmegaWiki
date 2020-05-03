<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

/**
 * @brief class to access Wordnik's API
 */
class WordnikExtension extends ExternalResources {

	private $wordApi; // < object to handle Wordnik's API

	/** @note see ExternalResources::__contruct
	 */
	function __construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId ) {
		parent::__construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId );
		preg_match( '/^Wordnik(.+)/', $this->source, $match );
		$this->sourceLabel = 'Wordnik ' . $match[1];

		global $myWordnikAPIKey;
		require_once __DIR__ . '/../../external/wordnik/wordnik/Swagger.php';
		$wgWldExtenalResourceLanguages = [
			85 => 'English'
		];
		$WordnikClient = new APIClient( $myWordnikAPIKey, 'http://api.wordnik.com/v4' );
		$this->wordApi = new WordApi( $WordnikClient );
		$this->sourceDictionary = 'all';
	}

	function execute() {
		// reconstruct spTitle
		$this->spTitle = 'Wordnik: ' . $this->spTitle;
		parent::execute();
	}

	function checkExternalDefinition() {
		// $example = $this->wordApi->getTopExample('big');
		// $exampleSentence = $example->text;
		$this->externalDefinition = $this->wordApi->getDefinitions( $this->search, null, $this->sourceDictionary, null, 'true' );
		$this->externalExists = true;
		if ( $this->externalDefinition ) {
			$this->externalExists = true;
		}
	}

	function setExternalDefinition() {
		$this->externalLexicalData = [];

		if ( $this->externalDefinition ) {
			foreach ( $this->externalDefinition as $definition ) {
				if ( $definition->sourceDictionary !== 'ahd-legacy' ) {
					$this->setExternalLexicalData( $definition );

				}
			}
		}
		$this->externalLexicalDataJSON = json_encode( $this->externalLexicalData );
		// line below for testing purpose when without internet for expression 'pig'
		// $this->getTestExternalDefinition();
		$this->wgOut->addHTML(
			'<div id="ext-data">' . $this->externalLexicalDataJSON . '</div>'
		);
	}

	/** Includes only definitions that are relevant. Filters out definitions
	 * 	that would be useless to the current program.
	 * @note currently, only related words have data that is relevant to this special page.
	 */
	function setExternalLexicalData( $definition ) {
		$includeDefinition = $this->includeDefinition( $definition );

		if ( $includeDefinition === true ) {
			$this->externalLexicalData[] = [
				'process' => null,
				'src' => $definition->sourceDictionary,
				'text' => $definition->text,
				'partOfSpeech' => $definition->partOfSpeech,
				'relatedWords' => $definition->relatedWords
			];
		}
	}

	private function includeDefinition( $definition ) {
		$includeDefinition = false;
		if ( $definition->relatedWords ) {
			foreach ( $definition->relatedWords as $rw ) {
				switch ( $rw->relationshipType ) {
					case 'synonym':
						$includeDefinition = true;
						break;
				}
			}
		}

		return $includeDefinition;
	}

	private function getTestExternalDefinition() {
		$this->externalLexicalDataJSON = <<<JSON
[
	{
		"process":null,
		"src":"wiktionary",
		"text":"earthenware, or an earthenware shard",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"An earthenware hot-water jar to warm a bed; a stone bed warmer",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"a pigeon.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"Any of several mammals of the genus Sus, having cloven hooves, bristles and a nose adapted for digging; especially the domesticated farm animal Sus scrofa.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A young swine, a piglet.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"The edible meat of such an animal; pork.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"Someone who overeats or eats rapidly and noisily.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A nasty or disgusting person.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A dirty or slovenly person.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A difficult problem.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A block of cast metal.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"The mold in which a block of metal is cast.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"A device for cleaning or inspecting the inside of an oil or gas pipeline, or for separating different substances within the pipeline. Named for the pig-like squealing noise made by their progress.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"a person who is obese to the extent of resembling a pig (the animal)",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"to give birth.",
		"partOfSpeech":"verb",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"To greedily consume (especially food).",
		"partOfSpeech":"verb",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wiktionary",
		"text":"To huddle or lie together like pigs, in one bed.",
		"partOfSpeech":"verb",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"A piggin.",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"synonym",
				"label2":null,
				"label3":null,
				"words":["piggin"],
				"gram":null,
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"variant",
				"label2":null,
				"label3":null,
				"words":["pigg"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"The young of swine, male or female; also, any swine; a hog.",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"synonym",
				"label2":null,
				"label3":null,
				"words":["hog"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"Any wild species of the genus Sus and related genera.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"An oblong mass of cast iron, lead, or other metal. See Mine pig, under Mine.",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"variant",
				"label2":null,
				"label3":null,
				"words":["mine"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"One who is hoggish; a greedy person.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"To bring forth (pigs); to bring forth in the manner of pigs; to farrow.",
		"partOfSpeech":"verb",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"form",
				"label2":null,
				"label3":null,
				"words":["pigged"],
				"gram":"imp. & p. p.",
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"form",
				"label2":null,
				"label3":null,
				"words":["pigging"],
				"gram":"p. pr. & vb. n.",
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"synonym",
				"label2":null,
				"label3":null,
				"words":["farrow"],
				"gram":null,
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"form",
				"label2":null,
				"label3":null,
				"words":["pigged"],
				"gram":"imp. & p. p.",
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"form",
				"label2":null,
				"label3":null,
				"words":["pigging"],
				"gram":"p. pr. & vb. n.",
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"gcide",
		"text":"To huddle or lie together like pigs, in one bed.",
		"partOfSpeech":"verb",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"A hog; a swine; especially, a porker, or young swine of either sex, the old male being called boar, the old female sow.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"The flesh of swine; pork.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"An oblong mass of metal that has been run while still molten into a mold excavated in sand; specifically, iron from the blast-furnace run into molds excavated in sand.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"A customary unit of weight for lead, 301 pounds.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"A very short space of time.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"To bring forth pigs; bring forth in the manner of pigs; litter.",
		"partOfSpeech":null,
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"To act as pigs; live like a pig; live or huddle as pigs: sometimes with an indefinite it.",
		"partOfSpeech":null,
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"An earthen vessel; any article of earthenware.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"A can for a chimney-top.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"A potsherd.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"Pig-iron collectively or any specified amount of iron pigs.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"century",
		"text":"In forestry, see rigging-sled.",
		"partOfSpeech":"noun",
		"relatedWords":[]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"domestic swine",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hyponym",
				"label2":null,
				"label3":null,
				"words":["porker"],
				"gram":null,
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["swine"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"live like a pig, in squalor",
		"partOfSpeech":"verb",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["live"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"give birth",
		"partOfSpeech":"verb",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["bear","give birth","birth","have","deliver"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"a person regarded as greedy and pig-like",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["selfish person"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"eat greedily",
		"partOfSpeech":"verb",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["eat"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"uncomplimentary terms for a policeman",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["police officer","policeman","officer"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"a crude block of metal (lead or iron) poured from a smelting furnace",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["ingot","metal bar","block of metal"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"a coarse obnoxious person",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hyponym",
				"label2":null,
				"label3":null,
				"words":["litterbug","litter lout","slattern","trollop","slut","litterer","slovenly woman"],
				"gram":null,
				"label4":null
			},
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["vulgarian"],
				"gram":null,
				"label4":null
			}
		]
	},
	{
		"process":null,
		"src":"wordnet",
		"text":"mold consisting of a bed of sand in which pig iron is cast",
		"partOfSpeech":"noun",
		"relatedWords":[
			{
				"label1":null,
				"relationshipType":"hypernym",
				"label2":null,
				"label3":null,
				"words":["mold","cast","mould"],
				"gram":null,
				"label4":null
			}
		]
	}
]
JSON;
		$this->tempExternalLexicalData = json_decode( $this->externalLexicalDataJSON );

		$this->externalLexicalData = [];

		foreach ( $this->tempExternalLexicalData as $definition ) {
			if ( $definition->src !== 'ahd-legacy' ) {
				$includeDefinition = $this->includeDefinition( $definition );

				if ( $includeDefinition ) {
					$this->externalLexicalData[] = $definition;
				}

			}
		}

		$this->externalLexicalDataJSON = json_encode( $this->externalLexicalData );
	}
}

class WordnikWiktionaryExtension extends WordnikExtension {
	function __construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId ) {
		parent::__construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId );
		$this->sourceDictionary = 'wiktionary';
	}
}

class WordnikWordnetExtension extends WordnikExtension {
	function __construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId ) {
		parent::__construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId );
		$this->sourceDictionary = 'wordnet';
	}
}
