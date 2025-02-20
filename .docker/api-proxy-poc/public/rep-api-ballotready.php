<?php

// // Request-validation
// if (empty($_REQUEST['address'])) {
//     exit('Missing required parameter: address');
// }

// $cmd = <<<HEREDOC
// curl -X 'POST' 'https://bpi.civicengine.com/graphql'      -H 'Content-Type: application/json'      -H 'Accept: application/json'      -H 'Authorization: Bearer H4DELQiMU7Z5W5vFggdZm-50oHZcJERflEVnsg8axFs'      --data '{ "query": "{ officeHolders ( location: { point: { latitude: 33.102120, longitude: -96.917640 } }, filterBy: { isCurrent: true }, first: 50 )  { nodes { id databaseId officeTitle isCurrent isVacant isAppointed startAt endAt person { fullName bioText firstName lastName officeHolders } } pageInfo { hasNextPage endCursor } } }" }'
// HEREDOC;

$cmd = <<<HEREDOC
curl -X 'POST' 'https://bpi.civicengine.com/graphql' \
     -H 'Content-Type: application/json' \
     -H 'Accept: application/json' \
     -H 'Authorization: Bearer H4DELQiMU7Z5W5vFggdZm-50oHZcJERflEVnsg8axFs' \
     --data '{ "query": "{ officeHolders ( location: { point: { latitude: 33.102120, longitude: -96.917640 } }, filterBy: { isCurrent: true }, first: 50 )  { nodes { id databaseId officeTitle isCurrent isVacant isAppointed startAt endAt person { fullName bioText firstName lastName officeHolders { nodes { id officeTitle isCurrent startAt endAt } } } } pageInfo { hasNextPage endCursor } } }" }'
HEREDOC;


// Hit the API
$cmd = trim($cmd);
$json = shell_exec($cmd);
// var_dump($json);

// Parse the data
$data = json_decode($json, true);
print_r($data);
