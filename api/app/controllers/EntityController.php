<?php

class EntityController extends Controller {

   
    public function sendErrorMessage($validator){
        $messages = $validator->messages();
        $s = "JSON does not validate. Violations:\n";
        foreach ($messages->all() as $message)
        {
            $s .= "$message\n";
        }
        App::abort(400, $s);
    }

    public function getEntities($customer_name) {
    		
        $customer = DB::table('customer')
        ->where('username', '=', $customer_name)
        ->first();

        if(isset($customer)){
			$db_entities = DB::table('entity')
            ->where('customer_id', '=', $customer->id)
            ->get();

			$entities = array();

			foreach($db_entities as $entity){
                if(isset($entity->body))
				    array_push($entities, json_decode($entity->body));
			}
			return json_encode($entities);
			
    	}else{
    		App::abort(404, 'Customer not found');
    	}
    }

    public function getAmenities($customer_name) {
        
        $customer = DB::table('customer')
        ->where('username', '=', $customer_name)
        ->first();

        if(isset($customer)){

            $db_amenities = DB::table('entity')
            ->where('customer_id', '=', $customer->id)
            ->where('type', '=', 'amenity')
            ->get();

            $amenities = array();

            foreach($db_amenities as $amenity){
                if(isset($amenity->body))
                    array_push($amenities, json_decode($amenity->body));                
            }
            return json_encode($amenities);
            
        }else{
            App::abort(404, 'Customer not found');
        }
    }

    public function getAmenityByName($customer_name, $name) {

    
        $customer = DB::table('customer')
        ->where('username', '=', $customer_name)
        ->first();

        if(isset($customer)){
            $amenity = DB::table('entity')
            ->where('customer_id', '=', $customer->id)
            ->where('type', '=', 'amenity')
            ->where('name', '=', $name)
            ->first();

            if(!isset($amenity)){
                App::abort(404, 'Amenity not found');
            }else{
                return $amenity->body;
            }
            
        }else{
            App::abort(404, 'Customer not found');
        }
    }


    public function getEntityByName($customer_name, $name) {

 
        $customer = DB::table('customer')
        ->where('username', '=', $customer_name)
        ->first();

        if(isset($customer)){

            $entity = DB::table('entity')
            ->where('customer_id', '=', $customer->id)
            ->where('name', '=', $name)
            ->first();

            if(!isset($entity)){
                App::abort(404, 'Entity not found');
            }else{
                return $entity->body;
            }
            
        }else{
            App::abort(404, 'Customer not found');
        }
    }

    public function createEntity($customer_name, $name) {

        $customer = DB::table('customer')
            ->where('username', '=', $customer_name)
            ->first();

        if(isset($customer)){

            /* we pass the basicauth so we can test against 
            this username with the url {customer_name}*/
            $username = Request::header('php-auth-user');

            if(!strcmp($customer_name, $username)){

                // Validation building ...
                Validator::extend('opening_hours', function($attribute, $value, $parameters)
                {
                    $now = date("Y-m-d h:m:s", time());
                    foreach($value as $opening_hour){
                        $opening_hour_validator = Validator::make(
                            $opening_hour,
                            array(
                                'opens' => 'required',
                                'closes' => 'required',
                                'validFrom' => 'required|after:'.$now,
                                'validThrough' => 'required|after:'.$now,
                                'dayOfWeek' => 'required|numeric|between:0,7'
                            )
                        );
                        if($opening_hour_validator->fails())
                            $this->sendErrorMessage($hours_validator);
                    }
                    return true;
                });

                Validator::extend('currency', function($attribute, $value, $parameters)
                {
                    $currencies = array('EUR', 'USD', 'YEN');
                    return in_array($value, $currencies);
                });
                Validator::extend('grouping', function($attribute, $value, $parameters)
                {
                    return in_array($value, array('hourly', 'daily', 'weekly', 'monthly', 'yearly'));
                });
                Validator::extend('price', function($attribute, $value, $parameters)
                {
                    
                    $price_validator = Validator::make(
                        $value,
                        array(
                            'currency' => 'required|currency',
                            'amount' => 'required|numeric|min:0',
                            'grouping' => 'required|grouping'
                        )
                    );
                    if($price_validator->fails())
                        $this->sendErrorMessage($price_validator);
                    return true;
                });

                Validator::extend('map', function($attribute, $value, $parameters)
                {
                    $map_validator = Validator::make(
                        $value,
                        array(
                            'img' => 'required|url',
                            'reference' => 'required'
                        )
                    );
                    if($map_validator->fails())
                        $this->sendErrorMessage($map_validator);
                    return true;
                });
                Validator::extend('location', function($attribute, $value, $parameters)
                {
                    $location_validator = Validator::make(
                        $value,
                        array(
                            'map' => 'required|map',
                            'floor' => 'required|numeric',
                            'building_name' => 'required'
                        )
                    );
                    if($location_validator->fails())
                        $this->sendErrorMessage($location_validator);
                    return true;
                });

                Validator::extend('amenities', function($attribute, $value, $parameters)
                {
                    $present = true;
                    if(count($value)){
                        //we check if amenities provided as input exists in database
                        $amenities = DB::table('entity')->where('type', '=', 'amenity')->lists('name');
                        foreach($value as $amenity){
                            $present = in_array($amenity, $amenities);
                        }
                    }
                    return $present;
                });

                //TODO : custom error messages
                Validator::extend('body', function($attribute, $value, $parameters)
                {
                    $body_validator = Validator::make(
                        $value,
                        array(
                            'name' => 'required',
                            'type' => 'required',
                            'description' => 'required',
                            'location' => 'required|location',
                            'price' => 'required|price',
                            'amenities' => 'amenities',
                            'contact' => 'required|url',
                            'support' => 'required|url',
                            'opening_hours' => 'required|opening_hours'
                        )
                    );

                    if($body_validator->fails())
                        $this->sendErrorMessage($body_validator);
                    return true;
                });

                $room_validator = Validator::make(
                    Input::all(),
                    array(
                        'name' => 'required',
                        'type' => 'required|alpha_dash',
                        'body' => 'required|body'
                    )
                );


                // Validator testing
                if (!$room_validator->fails()) {

                    DB::table('entity')->insert(
                        array(
                            'name' => Input::get('name'),
                            'type' => Input::get('type'),
                            'updated_at' => microtime(),
                            'created_at' => microtime(),
                            'body' => json_encode(Input::get('body')),
                            'customer_id' => $customer->id
                        )
                    );
                } else {
                    $this->sendErrorMessage($room_validator);
                } 
            }else{
               App::abort(403, "You can't modify entities from another customer");
            }
        }else{
            App::abort(404, 'Customer not found');
        }   
    }

    public function createAmenity($customer_name, $name) {


        $customer = DB::table('customer')
            ->where('username', '=', $customer_name)
            ->first();
        if(isset($customer)){
            
            /* we pass the basicauth so we can test against 
            this username with the url {customer_name}*/
            $username = Request::header('php-auth-user');
            if(!strcmp($customer_name, $username)){

                //TODO : custom error messages
                Validator::extend('schema', function($attribute, $value, $parameters)
                {
                    return json_encode($value) != null;
                });

                $amenity_validator = Validator::make(
                    Input::all(),
                    array(
                        'name' => 'required|unique:entity,name',
                        'description' => 'required',
                        'schema' => 'required|schema'
                    )
                );

                if(!$amenity_validator->fails()){
                    DB::table('entity')->insert(
                        array(
                            'name' => Input::get('name'),
                            'type' => 'amenity',
                            'updated_at' => time(),
                            'created_at' => time(),
                            'body' => json_encode(Input::get('schema')),
                            'customer_id' => $customer->id
                        )
                    );
                }else{
                    $this->sendErrorMessage($amenity_validator);
                }
                
            }else{
                App::abort(403, "You are not allowed to modify amenities from another customer");
            }
            
        }else{
            App::abort(404, 'Customer not found');
        }               
    }

    public function deleteAmenity($customer_name, $name) {
        
        
        $customer = DB::table('customer')
            ->where('username', '=', $customer_name)
            ->first();
        if(isset($customer)){

            /* we pass the basicauth so we can test against 
            this username with the url {customer_name}*/
            $username = Request::header('php-auth-user');

            if(!strcmp($customer_name, $username)){
            
                DB::table('entity')
                ->where('customer_id', '=', $customer->id)
                ->where('name', '=', $name)
                ->delete();
            }else{
                App::abort(403, "You're not allowed to delete amenities from another customer");
            }
            
        }else{
            App::abort(404, 'Customer not found');
        }
    }
}


?>