{
	"info": {
		"_postman_id": "7d8f8eef-8957-4fc7-8368-21dfa4331471",
		"name": "Celebrate Now",
		"schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
		"_exporter_id": "38717511",
		"_collection_link": "https://neat-net-team.postman.co/workspace/Hasan-Raza-WorkSpace~e83a1294-f679-4cfd-8681-d69fa0d4d2ee/collection/38717511-7d8f8eef-8957-4fc7-8368-21dfa4331471?source=collection_link"
	},
	"item": [
		{
			"name": "Auth",
			"item": [
				{
					"name": "Register",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 422\", function () {",
									"    pm.expect(pm.response.code).to.equal(422);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Errors object contains expected keys\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.errors).to.have.all.keys('email', 'contact_no');",
									"});",
									"",
									"",
									"pm.test(\"Each error message in the errors object is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.have.property('errors').that.is.an('object');",
									"    ",
									"    Object.keys(responseData.errors).forEach(function(key) {",
									"        responseData.errors[key].forEach(function(errorMessage) {",
									"            pm.expect(errorMessage).to.be.a('string').and.to.have.lengthOf.at.least(1, \"Error message should not be empty\");",
									"        });",
									"    });",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [
									""
								],
								"type": "text/javascript",
								"packages": {}
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"first_name\": \"Hasan\",\r\n  \"last_name\": \"Raza\",\r\n  \"email\": \"hasan.raza@celebratenow.com\",\r\n  \"contact_no\": \"030015534567\",\r\n  \"profile_type\": \"public\",\r\n  \"dob\": \"1998-06-15\",\r\n  \"password\": \"password123\",\r\n  \"password_confirmation\": \"password123\",\r\n  \"profile_image\": \"https://example.com/images/profile.png\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{base_url}}register",
							"host": [
								"{{base_url}}register"
							]
						}
					},
					"response": []
				},
				{
					"name": "Login",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 200\", function () {",
									"    pm.expect(pm.response.code).to.equal(200);",
									"});",
									"",
									"",
									"pm.test(\"Response has required fields: token and user object\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.all.keys('token', 'user');",
									"});",
									"",
									"",
									"pm.test(\"Token is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.token).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Token should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"User ID is a positive integer\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.user).to.exist.and.to.be.an('object');",
									"    pm.expect(responseData.user.id).to.be.a('number').and.to.be.above(0, \"User ID should be a positive integer\");",
									"});",
									"",
									"",
									"pm.test(\"Email is in a valid format\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.user).to.be.an('object');",
									"    pm.expect(responseData.user.email).to.exist;",
									"    pm.expect(responseData.user.email).to.match(/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/, \"Email format is invalid\");",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [
									""
								],
								"type": "text/javascript",
								"packages": {}
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{ \r\n     \"email\": \"hasan.raza@celebratenow.com\",\r\n   \"password\": \"password123\"\r\n}",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{base_url}}login",
							"host": [
								"{{base_url}}login"
							]
						}
					},
					"response": []
				},
				{
					"name": "Profile",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has the required fields\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must not be empty\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.have.lengthOf.at.least(1, \"Message field should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n//   \"name\": \"Hasan Raza\",\r\n  \"email\": \"hasan@celebratenow.com\",\r\n//   \"contact_no\": \"03001234567\",\r\n//   \"profile_type\": \"public\",\r\n  \"password\": \"12345678\"\r\n//   \"password_confirmation\": \"12345678\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}profile",
							"host": [
								"{{baseUrl}}profile"
							]
						}
					},
					"response": []
				},
				{
					"name": "Update Profile",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response content type is JSON\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "PUT",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n    \"name\": \"Hasan Raza\",\r\n    \"email\": \"hasan@celebratenow.com\",\r\n    \"contact_no\": \"03001214567\",\r\n    \"profile_type\": \"public\",\r\n    \"password\": \"12345678\",\r\n    \"profile_image\" : \"http://127.0.0.1:8000/storage/profile_images/8kAqcbQIkLYM55rDZ2X0FRnIXOPHSge15PUfc0rQ.jpg\"\r\n    //   \"password_confirmation\": \"12345678\"\r\n}",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}update-profile",
							"host": [
								"{{baseUrl}}update-profile"
							]
						}
					},
					"response": []
				},
				{
					"name": "Upload Image",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "formdata",
							"formdata": [
								{
									"key": "image",
									"type": "file",
									"src": "/C:/Users/hasan.raza/Pictures/Camera Roll/WIN_20250501_16_35_31_Pro.jpg"
								}
							]
						},
						"url": {
							"raw": "{{base_url}}upload-image",
							"host": [
								"{{base_url}}upload-image"
							]
						}
					},
					"response": []
				},
				{
					"name": "Deactivate Profile",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response should contain a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include(\"application/json\");",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "DELETE",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n    \"name\": \"Hasan Raza\",\r\n    \"email\": \"hasan@celebratenow.com\",\r\n    \"contact_no\": \"03001214567\",\r\n    \"profile_type\": \"public\",\r\n    \"password\": \"12345678\"\r\n    //   \"password_confirmation\": \"12345678\"\r\n}",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}deactivate",
							"host": [
								"{{baseUrl}}deactivate"
							]
						}
					},
					"response": []
				},
				{
					"name": "Change Password",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has the required 'message' field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type header is set to application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"old_password\": \"12345678\",\r\n  \"new_password\": \"123456781\",\r\n  \"new_password_confirmation\": \"123456781\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}change-password",
							"host": [
								"{{baseUrl}}change-password"
							]
						}
					},
					"response": []
				},
				{
					"name": "Get OTP (Forgot Password)",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 200\", function () {",
									"    pm.expect(pm.response.code).to.equal(200);",
									"});",
									"",
									"",
									"pm.test(\"Response has required fields\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.all.keys('message', 'otp');",
									"});",
									"",
									"",
									"pm.test(\"OTP is a non-negative integer\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.have.property('otp');",
									"    pm.expect(responseData.otp).to.be.a('number').and.to.be.at.least(0);",
									"});",
									"",
									"",
									"pm.test(\"The message must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text",
								"disabled": true
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"email\": \"hasan@celebratenow.com\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{base_url}}get-otp",
							"host": [
								"{{base_url}}get-otp"
							]
						}
					},
					"response": []
				},
				{
					"name": "Reset Password Using OTP",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 400\", function () {",
									"    pm.expect(pm.response.code).to.equal(400);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type should be application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text",
								"disabled": true
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"email\": \"hasan@celebratenow.com\",\r\n  \"otp\": \"672576\",\r\n  \"password\": \"newpassword123\",\r\n  \"password_confirmation\": \"newpassword123\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}reset-password",
							"host": [
								"{{baseUrl}}reset-password"
							]
						}
					},
					"response": []
				},
				{
					"name": "Logout",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 200\", function () {",
									"    pm.expect(pm.response.code).to.eql(200);",
									"});",
									"",
									"",
									"pm.test(\"Response should have a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n//   \"name\": \"Hasan Raza\",\r\n  \"email\": \"hasan@celebratenow.com\",\r\n//   \"contact_no\": \"03001234567\",\r\n//   \"profile_type\": \"public\",\r\n  \"password\": \"12345678\"\r\n//   \"password_confirmation\": \"12345678\"\r\n}\r\n",
							"options": {
								"raw": {
									"language": "json"
								}
							}
						},
						"url": {
							"raw": "{{baseUrl}}logout",
							"host": [
								"{{baseUrl}}logout"
							]
						}
					},
					"response": []
				}
			]
		},
		{
			"name": "Post",
			"item": [
				{
					"name": "Create Post",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Message should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response content type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get(\"Content-Type\")).to.include(\"application/json\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"caption\": \"Celebrating art and colors!\",\r\n  \"photo\": \"https://yourdomain.com/storage/post_images/img123.jpg\",\r\n  \"event_category_id\": 1,\r\n  \"privacy\": \"public\",\r\n  \"tag_user_ids\": [2, 3]\r\n}"
						},
						"url": {
							"raw": "{{baseUrl}}posts",
							"host": [
								"{{baseUrl}}posts"
							]
						}
					},
					"response": []
				},
				{
					"name": "Get Posts",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "formdata",
							"formdata": [
								{
									"key": "caption",
									"value": " Enjoying the sunset!",
									"type": "text"
								},
								{
									"key": "photo",
									"value": " sample.jpg (file upload)",
									"type": "text"
								},
								{
									"key": "event_category_id",
									"value": " 2",
									"type": "text"
								},
								{
									"key": "privacy",
									"value": " public",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 4",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 5",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}posts",
							"host": [
								"{{baseUrl}}posts"
							]
						}
					},
					"response": []
				},
				{
					"name": "Post with Count",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 404\", function () {",
									"    pm.expect(pm.response.code).to.equal(404);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Response contains required fields in the trace array\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.trace).to.be.an('array').that.is.not.empty;",
									"",
									"    responseData.trace.forEach(function(item) {",
									"        pm.expect(item).to.have.all.keys('file', 'line', 'function', 'class', 'type');",
									"    });",
									"});",
									"",
									"",
									"pm.test(\"Trace array is not empty when status is 404\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(pm.response.code).to.equal(404);",
									"    pm.expect(responseData).to.have.property('trace').that.is.an('array').and.is.not.empty;",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "formdata",
							"formdata": [
								{
									"key": "caption",
									"value": " Enjoying the sunset!",
									"type": "text"
								},
								{
									"key": "photo",
									"value": " sample.jpg (file upload)",
									"type": "text"
								},
								{
									"key": "event_category_id",
									"value": " 2",
									"type": "text"
								},
								{
									"key": "privacy",
									"value": " public",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 4",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 5",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}posts/1/with-counts",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1",
								"with-counts"
							]
						}
					},
					"response": []
				},
				{
					"name": "Show Single Post",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "formdata",
							"formdata": [
								{
									"key": "caption",
									"value": " Enjoying the sunset!",
									"type": "text"
								},
								{
									"key": "photo",
									"value": " sample.jpg (file upload)",
									"type": "text"
								},
								{
									"key": "event_category_id",
									"value": " 2",
									"type": "text"
								},
								{
									"key": "privacy",
									"value": " public",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 4",
									"type": "text"
								},
								{
									"key": "tag_user_ids[]",
									"value": " 5",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}posts/1",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1"
							]
						}
					},
					"response": []
				}
			]
		},
		{
			"name": "Event Category",
			"item": [
				{
					"name": "Create Event Category",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response has the correct Content-Type header\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "name",
									"value": "Others",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{base_url}}event-categories",
							"host": [
								"{{base_url}}event-categories"
							]
						}
					},
					"response": []
				},
				{
					"name": "Get Event Category",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response has the correct Content-Type header\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [
									""
								],
								"type": "text/javascript",
								"packages": {}
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "name",
									"value": "Others",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}event-categories",
							"host": [
								"{{baseUrl}}event-categories"
							]
						}
					},
					"response": []
				},
				{
					"name": "Update Event Category",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message should be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "PUT",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "name",
									"value": " Art Exhibition",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}event-categories/1",
							"host": [
								"{{baseUrl}}event-categories"
							],
							"path": [
								"1"
							]
						}
					},
					"response": []
				},
				{
					"name": "Delete Event Category",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Response Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "DELETE",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "name",
									"value": " Art Exhibition",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}event-categories/2",
							"host": [
								"{{baseUrl}}event-categories"
							],
							"path": [
								"2"
							]
						}
					},
					"response": []
				}
			]
		},
		{
			"name": "Post Likes/Comments/Replies/Tag",
			"item": [
				{
					"name": "Like a Post",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 500\", function () {",
									"    pm.expect(pm.response.code).to.equal(500);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The exception field must be non-empty\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.exception).to.exist.and.to.have.lengthOf.at.least(1, \"Exception field should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Response has a valid structure for the trace array\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.trace).to.be.an('array').that.is.not.empty;",
									"",
									"    responseData.trace.forEach(function(traceItem) {",
									"        pm.expect(traceItem).to.be.an('object');",
									"        pm.expect(traceItem).to.have.all.keys('file', 'line', 'function', 'class', 'type');",
									"        pm.expect(traceItem.file).to.be.a('string');",
									"        pm.expect(traceItem.line).to.be.a('number');",
									"        pm.expect(traceItem.function).to.be.a('string');",
									"        pm.expect(traceItem.class).to.be.a('string');",
									"        pm.expect(traceItem.type).to.be.a('string');",
									"    });",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{baseUrl}}posts/1/like",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1",
								"like"
							]
						}
					},
					"response": []
				},
				{
					"name": "Tag People to Post",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Message should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include(\"application/json\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"user_ids\": [4, 5]\r\n}\r\n"
						},
						"url": {
							"raw": "{{baseUrl}}posts/1/tag",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1",
								"tag"
							]
						}
					},
					"response": []
				},
				{
					"name": "Comment on a Post",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 200\", function () {",
									"    pm.expect(pm.response.code).to.equal(200);",
									"});",
									"",
									"",
									"pm.test(\"Response has required fields\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.all.keys('post_id', 'user_id', 'body', 'emojis', 'updated_at', 'created_at', 'id');",
									"});",
									"",
									"",
									"pm.test(\"The body must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.have.property('body').that.is.a('string').and.has.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Emojis is an array and contains valid emoji strings\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.have.property('emojis').that.is.an('array');",
									"    responseData.emojis.forEach(function(emoji) {",
									"        pm.expect(emoji).to.be.a('string').that.matches(/^[\\u{1F600}-\\u{1F64F}\\u{1F300}-\\u{1F5FF}\\u{1F680}-\\u{1F6FF}\\u{1F700}-\\u{1F77F}\\u{1F900}-\\u{1F9FF}\\u{2600}-\\u{26FF}\\u{2700}-\\u{27BF}]+$/u, \"Invalid emoji string\");",
									"    });",
									"});",
									"",
									"",
									"pm.test(\"User ID must be a positive integer\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.user_id).to.be.a('number').and.to.be.above(0, \"User ID should be a positive integer\");",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token_2}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"body\": \"Wow! Looks amazing 😍\",\r\n  \"emojis\": [\"🔥\", \"🎉\"]\r\n}\r\n"
						},
						"url": {
							"raw": "{{baseUrl}}posts/1/comment",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1",
								"comment"
							]
						}
					},
					"response": []
				},
				{
					"name": "Reply to a Comment",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has Content-Type of application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response body contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"The message field must be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\r\n  \"body\": \"Absolutely agree!\",\r\n  \"emojis\": [\"👌\"]\r\n}\r\n"
						},
						"url": {
							"raw": "{{baseUrl}}comments/1/reply",
							"host": [
								"{{baseUrl}}comments"
							],
							"path": [
								"1",
								"reply"
							]
						}
					},
					"response": []
				},
				{
					"name": "Like a Comment",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    pm.expect(responseData).to.be.an('object').that.has.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message field should be a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response has the correct Content-Type header\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.equal('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{baseUrl}}comments/1/like",
							"host": [
								"{{baseUrl}}comments"
							],
							"path": [
								"1",
								"like"
							]
						}
					},
					"response": []
				}
			]
		},
		{
			"name": "Follow/Unfollow",
			"item": [
				{
					"name": "Follow a User",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 404\", function () {",
									"    pm.expect(pm.response.code).to.eql(404);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Response has an exception field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('exception');",
									"});",
									"",
									"",
									"pm.test(\"Response contains Trace array\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('trace').that.is.an('array');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{baseUrl}}follow-toggle/3",
							"host": [
								"{{baseUrl}}follow-toggle"
							],
							"path": [
								"3"
							]
						}
					},
					"response": []
				},
				{
					"name": "Get Followers",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has Content-Type of application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.equal('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response body is not null\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.not.be.null;",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token_2}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}followers",
							"host": [
								"{{baseUrl}}followers"
							]
						}
					},
					"response": []
				},
				{
					"name": "Get Followings",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response should contain a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response has the correct Content-Type header\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.equal('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript",
								"packages": {}
							}
						},
						{
							"listen": "prerequest",
							"script": {
								"exec": [],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}following",
							"host": [
								"{{baseUrl}}following"
							]
						}
					},
					"response": []
				},
				{
					"name": "All Followers and Followings",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response has the correct Content-Type header\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.equal('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response body is a valid JSON object\", function () {",
									"    const responseData = pm.response.json();",
									"    pm.expect(responseData).to.be.an('object');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}my-network",
							"host": [
								"{{baseUrl}}my-network"
							]
						}
					},
					"response": []
				}
			]
		},
		{
			"name": "Feeds",
			"item": [
				{
					"name": "Those I follow (public + private)",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.eql(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json');",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}feed/following-posts",
							"host": [
								"{{baseUrl}}feed"
							],
							"path": [
								"following-posts"
							]
						}
					},
					"response": []
				},
				{
					"name": "One post with comments, replies, likes, tagged users",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response contains a message field\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.at.least(1, \"Value should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response has Content-Type of application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.include(\"application/json\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}posts/1/details",
							"host": [
								"{{baseUrl}}posts"
							],
							"path": [
								"1",
								"details"
							]
						}
					},
					"response": []
				},
				{
					"name": "All public posts with counts",
					"event": [
						{
							"listen": "test",
							"script": {
								"exec": [
									"pm.test(\"Response status code is 401\", function () {",
									"    pm.expect(pm.response.code).to.equal(401);",
									"});",
									"",
									"",
									"pm.test(\"Response should contain the required field 'message'\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData).to.have.property('message');",
									"});",
									"",
									"",
									"pm.test(\"Message is a non-empty string\", function () {",
									"    const responseData = pm.response.json();",
									"    ",
									"    pm.expect(responseData).to.be.an('object');",
									"    pm.expect(responseData.message).to.exist.and.to.be.a('string').and.to.have.lengthOf.above(0, \"Message should not be empty\");",
									"});",
									"",
									"",
									"pm.test(\"Response time is less than 200ms\", function () {",
									"    pm.expect(pm.response.responseTime).to.be.below(200);",
									"});",
									"",
									"",
									"pm.test(\"Content-Type is application/json\", function () {",
									"    pm.expect(pm.response.headers.get('Content-Type')).to.eql('application/json');",
									"});"
								],
								"type": "text/javascript"
							}
						}
					],
					"protocolProfileBehavior": {
						"disableBodyPruning": true
					},
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "Accept",
								"value": "application/json",
								"type": "text"
							},
							{
								"key": "Authorization",
								"value": "{{token}}",
								"type": "text"
							}
						],
						"body": {
							"mode": "urlencoded",
							"urlencoded": [
								{
									"key": "body",
									"value": " Absolutely agree!",
									"type": "text"
								},
								{
									"key": "emojis[]",
									"value": " 🙌",
									"type": "text"
								}
							]
						},
						"url": {
							"raw": "{{baseUrl}}feed/all-posts",
							"host": [
								"{{baseUrl}}feed"
							],
							"path": [
								"all-posts"
							]
						}
					},
					"response": []
				}
			]
		}
	]
}