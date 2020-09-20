<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Quote;
use App\Repository\QuoteRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class QuoteController extends AbstractController
{
    /**
     * Check if json string is valid
     * 
     * @param $string
     * @return bool
     */

    private function isValidJson($string)
    {
        try {
            json_decode($string);
        } catch (\Exception $e) {
            return false;
        }

        return (json_last_error() == JSON_ERROR_NONE);
    }

    private function checkRequiredData($request) {
        $fields = ['age', 'postcode', 'regNo'];
        foreach($fields as $field)
        {
            if(empty($request->request->get($field))) {
                return false;
            }
        }
        return true;
    }

    private function getBasePremium()
    {
        $conn = $this->getDoctrine()->getManager()->getConnection();

        $sql = 'SELECT base_premium FROM base_premium LIMIT 0, 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $base_premium = $stmt->fetchColumn(0);

        return $base_premium;
    }

    private function getAgeRating($age)
    {
        $conn = $this->getDoctrine()->getManager()->getConnection();

        $sql = 'SELECT rating_factor FROM age_rating WHERE age = '.$age.' LIMIT 0, 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rating_factor = $stmt->fetchColumn();

        if (empty($rating_factor)) {
            $rating_factor = 1;
        }

        return $rating_factor;
    }

    private function getPostCodeRating($postcode)
    {
        $conn = $this->getDoctrine()->getManager()->getConnection();

        // Getting first part of UK post code
        if (preg_match("(([A-Za-z]{1,2}[0-9]{1,2})($|[ 0-9]))", trim($postcode), $match)) { // Caters for BA12 1AB and B1 2AB postcode formats
            $region = $match[1];
        } elseif (preg_match("(([A-Za-z]{1,2}[0-9]{1,2}[A-Za-z]{1})($|[ 0-9]))", trim($postcode), $match)) { // Caters for EC1M 1AB London postcode formats
            $region = $match[1];
        }

        $sql = 'SELECT rating_factor FROM postcode_rating WHERE postcode_area = "'.$region.'" LIMIT 0, 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rating_factor = $stmt->fetchColumn();

        if (empty($rating_factor)) {
            $rating_factor = 1;
        }

        return $rating_factor;
    }

    private function getAbiRating($abicode)
    {
        if(isset($abicode)) {
            $conn = $this->getDoctrine()->getManager()->getConnection();

            $sql = 'SELECT * FROM abi_code_rating WHERE abi_code = "'.$abicode.'"';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rating_factor = $stmt->fetchAll();

            if (count($rating_factor) > 0) {
                $rating_factor = $rating_factor[0]['rating_factor'];
            } else {
                $rating_factor = 1;
            }
        } else {
            $rating_factor = 1;
        }

        return $rating_factor;
    }

    private function getAbiByRegNo($regNo)
    {
        // Getting Abi Code from 3rd party API
        $abi = @file_get_contents("https://dvlasearch.appspot.com/DvlaSearch?apikey=DvlaSearchDemoAccount&licencePlate=".$regNo.""); // Function with "@" hides error message on failure
        
        // Handling function's response
        if($abi === FALSE) {
            return;
        } else {
            // Checking if json is valid, if not then assume that abi rating is 1
            if($this->isValidJson($abi))
            {
                // Getting data into array from json response
                $abi = json_decode($abi, true);
                
                // Checking if there is no error
                if(!isset($abi["error"]) && isset($abi["vin"])){
                    return $abi["vin"];
                } else {
                    return;
                }
            } else {
                return;
            }
        }
    }

    private function postcode_check(&$toCheck)
    {
        // Permitted letters depend upon their position in the postcode.
        $alpha1 = "[abcdefghijklmnoprstuwyz]";                          // Character 1
        $alpha2 = "[abcdefghklmnopqrstuvwxy]";                          // Character 2
        $alpha3 = "[abcdefghjkstuw]";                                   // Character 3
        $alpha4 = "[abehmnprvwxy]";                                     // Character 4
        $alpha5 = "[abdefghjlnpqrstuwxyz]";                             // Character 5

        // Expression for postcodes: AN NAA, ANN NAA, AAN NAA, and AANN NAA with a space
        // Or AN, ANN, AAN, AANN with no whitespace
        $pcexp[0] = '^('.$alpha1.'{1}'.$alpha2.'{0,1}[0-9]{1,2})([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})?$';

        // Expression for postcodes: ANA NAA
        // Or ANA with no whitespace
        $pcexp[1] = '^('.$alpha1.'{1}[0-9]{1}'.$alpha3.'{1})([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})?$';

        // Expression for postcodes: AANA NAA
        // Or AANA With no whitespace
        $pcexp[2] = '^('.$alpha1.'{1}'.$alpha2.'[0-9]{1}'.$alpha4.')([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})?$';

        // Exception for the special postcode GIR 0AA
        // Or just GIR
        $pcexp[3] = '^(gir)([[:space:]]{0,})?(0aa)?$';

        // Standard BFPO numbers
        $pcexp[4] = '^(bfpo)([[:space:]]{0,})([0-9]{1,4})$';

        // c/o BFPO numbers
        $pcexp[5] = '^(bfpo)([[:space:]]{0,})(c\/o([[:space:]]{0,})[0-9]{1,3})$';

        // Overseas Territories
        $pcexp[6] = '^([a-z]{4})([[:space:]]{0,})(1zz)$';

        // Anquilla
        $pcexp[7] = '^(ai\-2640)$';

        // Load up the string to check, converting into lowercase
        $postcode = strtolower($toCheck);

        // Assume we are not going to find a valid postcode
        $valid = false;

        // Check the string against the six types of postcodes
        foreach ($pcexp as $regexp) {
            if (preg_match('/'.$regexp.'/i', $postcode, $matches)) {

                // Load new postcode back into the form element
                $postcode = strtoupper($matches[1]);
                if (isset($matches[3])) {
                    $postcode .= ' '.strtoupper($matches[3]);
                }

                // Take account of the special BFPO c/o format
                $postcode = preg_replace('/C\/O/', 'c/o ', $postcode);

                // Remember that we have found that the code is valid and break from loop
                $valid = true;
                break;
            }
        }

        // Return with the reformatted valid postcode in uppercase if the postcode was valid
        if ($valid) {
            $toCheck = $postcode;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @Route("/api/create", name="new_qoute")
     * 
     */
    public function create(Request $request)
    {
        $response = new Response();
        //$request = Request::createFromGlobals();

        // Getting content & type
        $content = $request->getContent();
        $contentType = $request->headers->get('content-type');

        if (strcasecmp($request->getMethod(), 'POST') != 0) { // Making sure that it is a POST request
            $response->setContent(json_encode(array(
                'error' => 'Request method must be POST!',
            )));
            $response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
        } elseif (strcasecmp($contentType, 'application/json') != 0) { // Making sure that the content type of the POST request has been set to application/json
            $response->setContent(json_encode(array(
                'error' => 'Content type must be: application/json',
            )));
            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
        } elseif (!$this->isValidJson($content)) { // Making sure that content from request is valid json
            $response->setContent(json_encode(array(
                'error' => 'Request is not valid JSON',
            )));
            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
        } else {
            $data = json_decode($request->getContent(), true); // Decoding json request
            $request->request->replace(is_array($data) ? $data : array()); //Fixing request data

            if (!empty($request->request->all())) { // Making sure request is not empty
                if($this->checkRequiredData($request)) {
                    $entityManager = $this->getDoctrine()->getManager();

                    // Retrieving data into one array from json request
                    $data = array();
                    $data['postCode'] = strtoupper($request->request->get('postcode'));
                    $data['age'] = $request->request->get('age');
                    $data['regNo'] = strtoupper($request->request->get('regNo'));

                    // removing whitespace from regNo
                    $data['regNo'] = preg_replace('/\s/', '', $data['regNo']);

                    if(preg_match("/(?<Current>^[A-Z]{2}[0-9]{2}[A-Z]{3}$)|(?<Prefix>^[A-Z][0-9]{1,3}[A-Z]{3}$)|(?<Suffix>^[A-Z]{3}[0-9]{1,3}[A-Z]$)|(?<DatelessLongNumberPrefix>^[0-9]{1,4}[A-Z]{1,2}$)|(?<DatelessShortNumberPrefix>^[0-9]{1,3}[A-Z]{1,3}$)|(?<DatelessLongNumberSuffix>^[A-Z]{1,2}[0-9]{1,4}$)|(?<DatelessShortNumberSufix>^[A-Z]{1,3}[0-9]{1,3}$)|(?<DatelessNorthernIreland>^[A-Z]{1,3}[0-9]{1,4}$)|(?<DiplomaticPlate>^[0-9]{3}[DX]{1}[0-9]{3}$)/", $data["regNo"])) {
                        // Checking if post code is valid
                        if ($this->postcode_check($data['postCode']) !== FALSE) {
                            // Checking if age is valid (must be number, not text/string, not contain whitespace and etc.)
                            if (preg_match("/^[1-9][0-9]*$/", $data["age"])) {

                                // Create Quote object
                                $quote = new Quote();

                                $quote->setPolicyNumber(uniqid()); // Giving unique ID for Policy Number

                                // Set Abi Code by using regNo (3rd party API)
                                $quote->setAbiCode($this->getAbiByRegNo($data['regNo']));
                                $quote->setAge($data['age']);
                                $quote->setPostCode($data['postCode']);
                                $quote->setRegno($data['regNo']);

                                // Set Premium
                                $quote->setPremium($this->getBasePremium() *
                                    $this->getAgeRating($quote->getAge()) *
                                    $this->getPostCodeRating($quote->getPostCode()) *
                                    $this->getAbiRating($quote->getAbiCode())
                                );

                                // Telling Doctrine you want to (eventually) save the Quote (no queries yet)
                                $entityManager->persist($quote);

                                // Actually executes the queries (i.e. the INSERT query)
                                $entityManager->flush();

                                $response->setContent(json_encode(array(
                                    'success' => "Quote successfully saved!",
                                )));
                                $response->setStatusCode(Response::HTTP_CREATED);
                            } else {
                                $response->setContent(json_encode(array(
                                    'error' => 'Age is not valid!',
                                )));
                                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                            }
                        } else {
                            $response->setContent(json_encode(array(
                                'error' => 'Post code is not valid!',
                            )));
                            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                        }
                    } else {
                        $response->setContent(json_encode(array(
                            'error' => 'Number plate is not valid!',
                        )));
                        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                    }
                } else {
                    $response->setContent(json_encode(array(
                        'error' => 'Request does not have all required data!',
                    )));
                    $response->setStatusCode(Response::HTTP_BAD_REQUEST); 
                }
            } else {
                $response->setContent(json_encode(array(
                    'error' => 'Request can not be left empty!',
                )));
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            }
        }
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
