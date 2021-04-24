<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class HomeController extends AbstractController {
    
    
    public $base_url = "http://api.ipstack.com/";
    public  $api_key="2994ab6f2c5642a0c9dc8c40578707d2";
    private  $client;//fot http client request 
    
   
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @Route("/")
     * @Method({"GET"})
     */
    public function index() : Response
    {
        return $this->render('home/index.html.twig');
    }

     /**
     * @Route("/uploadfile")
     * @Method({"POST"})
     */
    public function uploadFile(Request $request){
               

       //check file type = csv
       //dd($request->files->get('csv')->getClientMimeType());//web browser set the mime type
       if($request->files->get('csv')->getClientMimeType() == "text/csv"){
            
            $finalResult = [];
            $ipContinentCode=[];
            

            $file = $request->files->get('csv');//uploaded file csv

            // open and read the file uploaded 
            if (($handle = fopen($file, "r")) !== FALSE) {
 
                /* read countryInfo.txt File */
                /* return array(prefix,continent) */
                $phoneContinentCodes = $this->getContinentPrefixCodes();
                /* end countryCode File */
                
               
                // iterate over every line of the csv file upload by user
                while (($raw_string = fgets($handle)) !== false) {

                    
                    // parse the raw csv string
                    $row = str_getcsv($raw_string); // index 3 = phone number , index 4 = ip address
                    
                    
                    //fill array with [ip,continent_code]
                    //if the ip exist in the array , no request again from api
                    if( !empty($row[4]) && !isset($ipContinentCode[$row[4]]) ){//ip address array
                        $continent_code = $this->get_continent_code_for_ip($row[4]);//get the continent for this ip $row[4]
                        if($continent_code)
                          $ipContinentCode[ $row[4] ] = $continent_code;
                    } 


                    if(!empty($row[3])){//row[3] = phone number

                        //if the customer id not exist in the array need to add with start values
                        if(!isset($finalResult[$row[0]])){
                                        
                            $finalResult[$row[0]] = [
                                "count_same_continent"=>0,
                                "total_duration_same_continent"=>0,
                                "total_all_calls"=>0,
                                "total_duration_all_calls"=>0
                            ];
                                                 
                        }
                        

                        //loop over all prefix keys and when match stop loop
                        // array(prefix,continent)
                        foreach (array_keys($phoneContinentCodes) as $k) {  

                            //if the current number start with prefix match
                            if(substr($row[3], 0, strlen($k)) == $k){
                             
                                // if the  continent_code from phone == continent_code from ip
                                 if($phoneContinentCodes[$k] == $ipContinentCode[$row[4]]){
                                   
                                    //dd($row[3],$row[4],$phoneContinentCodes[$k],$ipContinentCode[$row[4]]);       
                                    
                                    //counters and additions 
                                    $finalResult[$row[0]]['count_same_continent']++;    
                                    $finalResult[$row[0]]['total_duration_same_continent']+=intval($row[2]);  
                                    $finalResult[$row[0]]['total_all_calls']++;   
                                    $finalResult[$row[0]]['total_duration_all_calls']+= intval($row[2]);  
                                   
                                
                                 }else{
                                    
                                    $finalResult[$row[0]]['total_all_calls']++;    
                                    $finalResult[$row[0]]['total_duration_all_calls']+= intval($row[2]);    
                                        
                                 }
                                               
                                   break;//stop looping and go to next line in csv
                             }  

                        }  

                    } 
                   
                }

                //dd($finalResult);

                fclose($handle);
            }

       }else{
           //return json with error its not csv file;
            $response = new Response(json_encode(array("status"=>false,"error_msg"=>"server error : please upload csv file")));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
       }

            //return the result 
            $response = new Response(json_encode(array("status"=>true,"data"=>$finalResult)));
            $response->headers->set('Content-Type', 'application/json');

            return $response;
    }
    
    // get continent_code from ip stack
    public function get_continent_code_for_ip($ip){
        //i used the symfony/http-client component
       
        try {
                $response = $this->client->request(
                    'GET', 
                    $this->base_url.$ip."?access_key=".$this->api_key."&fields=continent_code"//only the continent code field from api
                );
                
                $statusCode = $response->getStatusCode();
            
                $result = $response->toArray();
        
                //dd($statusCode,$result);
        
                if(isset($statusCode) && $statusCode == 200 && isset($result["continent_code"])){
        
                    return $result['continent_code'];

                }else{
                    return false;
                }

        } catch (\Throwable $th) {
            return false;
        }
        
    }

    //read countryInfo.txt and parse prefix and continent code
    //return array with [prefix,continent_code]
    public function getContinentPrefixCodes(){

        try {
            
            $continentPrefixArr=array();//[prefix,continent_code]
            
            if( ($fp = fopen( $this->getParameter('public_directory')."/countryInfo.txt", 'r')) !== FALSE){

                while (!feof($fp))//loop on all lines is the text file
                {
                    $line=fgets($fp);

                     //start to add key value after the line that have the #iso start string
                    if(empty($continentPrefixArr) && strpos($line, "#ISO") === 0){
                       
                        $row = explode("\t", $line);
                        //array_push($continentPrefixArr,$row);
                        $continentPrefixArr[$row[12]]=$row[8]; 
                    
                   
                    }elseif(!empty($continentPrefixArr)){
                        
                        $row = explode("\t", $line);
                        $charsToRemove = ["+","-"," "];    
                        if(!empty($row[12]) && !empty($row[8])){
                            
                            //if the prefix have more then on number like "+1-809 and 1-829"
                            if(strpos($row[12], 'and') !== false){//looking for the and string
                                
                                //More than one number
                                $moreThenOnePrefix = explode("and", $row[12]);
                                //dd($moreThenOnePrefix); 
                                foreach($moreThenOnePrefix as $v){

                                    $v = str_replace($charsToRemove, "", $v);
                                    if(!empty($v))     
                                     $continentPrefixArr[$v] = $row[8]; 

                                } 
                            }else{

                                $row[12] = str_replace($charsToRemove, "", $row[12]);
                               
                                if(!empty($row[12]))   
                                 $continentPrefixArr[ $row[12] ] = $row[8]; //need to remove +1-X and the plus sign +    
                            }


                        }
                                   
                    }
        
                }
                fclose($fp);//close file
            
            }
            
           // dd($continentPrefixArr);
            return $continentPrefixArr;


        } catch (\Throwable $th) {
           // throw $th;
           return array();
        }       
       /*$array = array(
              376 => "EU",
              971 => "AS",
              93 => "AS",
              "+1-268" => "NA",
              "+1-264" => "NA",
              43 => "EU",
              61 => "OC",
              297 => "NA",
              "+358-18" => "EU",
              387 => "EU",
              "+1-246" => "NA"
              );
              */
         

        /*End read file*/
        
    }

    
}   
