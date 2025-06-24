<?php

require_once __DIR__ . '/../controllers/menu.php';
require_once __DIR__ . '/../lib/wejv.php';

use PHPUnit\Framework\TestCase;

/**
 * Test case for the menu controller update_recipe functionality
 */
class MenuControllerUpdateTest extends TestCase
{
    private $wejvMock;

    protected function setUp(): void
    {
        // Create a mock of the Wejv class
        $this->wejvMock = $this->createMock(Wejv::class);

        // Set the global $wejv variable to our mock
        global $wejv;
        $wejv = $this->wejvMock;
    }

    /**
     * Helper method to simulate $_POST and $_FILES globals
     */
    private function setupPostData($postData, $fileData = null)
    {
        $_POST = $postData;

        if ($fileData) {
            $_FILES = $fileData;
        } else {
            $_FILES = [];
        }
    }

    /**
     * Test successful recipe update with all fields
     */
    public function testUpdateRecipeSuccess()
    {
        // Mock input data
        $postData = [
            'id' => 123,
            'name' => 'Updated Recipe',
            'prepare_time' => 30,
            'person_num' => 4,
            'description' => 'Updated description',
            'preparation' => 'Updated preparation steps',
            'ingredients' => json_encode(['ingredient1', 'ingredient2']),
            'genres' => json_encode(['Italian', 'Vegetarian']),
            'tags' => json_encode(['Quick', 'Easy'])
        ];

        // Set up expected data to be passed to updateRecipe
        $expectedData = [
            'id' => 123,
            'name' => 'Updated Recipe',
            'prepareTime' => 30,
            'personNum' => 4,
            'description' => 'Updated description',
            'preparation' => 'Updated preparation steps',
            'ingredients' => json_encode(['ingredient1', 'ingredient2']),
            'genres' => ['Italian', 'Vegetarian'],
            'tags' => ['Quick', 'Easy']
        ];

        // Setup expected response
        $expectedResponse = ['success' => true, 'id' => 123];

        // Configure mock
        $this->wejvMock->expects($this->once())
            ->method('updateRecipe')
            ->with($this->equalTo($expectedData))
            ->willReturn($expectedResponse);

        // Setup the request
        $this->setupPostData($postData);

        // Capture output
        ob_start();
        update_recipe();
        $output = ob_get_clean();

        // Assert the output is as expected
        $this->assertEquals(json_encode($expectedResponse), $output);
    }

    /**
     * Test recipe update with image upload
     */
    public function testUpdateRecipeWithImage()
    {
        // Mock input data
        $postData = [
            'id' => 123,
            'name' => 'Recipe With Image',
            'prepare_time' => 45,
            'person_num' => 2,
            'description' => 'Description with image',
            'preparation' => 'Preparation steps',
            'ingredients' => json_encode(['ingredient1', 'ingredient2']),
            'genres' => json_encode(['Italian']),
            'tags' => json_encode(['Fancy'])
        ];

        // Mock file upload
        $fileData = [
            'image' => [
                'name' => 'test_image.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/phpXXXX',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        // Mock image conversion result
        $mockImageData = 'binary-webp-data';

        // Set up expected data to be passed to updateRecipe
        $expectedData = [
            'id' => 123,
            'name' => 'Recipe With Image',
            'prepareTime' => 45,
            'personNum' => 2,
            'description' => 'Description with image',
            'preparation' => 'Preparation steps',
            'ingredients' => json_encode(['ingredient1', 'ingredient2']),
            'genres' => ['Italian'],
            'tags' => ['Fancy'],
            'img' => $mockImageData
        ];

        // Setup expected response
        $expectedResponse = ['success' => true, 'id' => 123];

        // Configure mock for image conversion
        $this->wejvMock->expects($this->once())
            ->method('convertToWebP')
            ->with('/tmp/phpXXXX', 'image/jpeg')
            ->willReturn($mockImageData);

        // Configure mock for update
        $this->wejvMock->expects($this->once())
            ->method('updateRecipe')
            ->with($this->equalTo($expectedData))
            ->willReturn($expectedResponse);

        // Setup the request
        $this->setupPostData($postData, $fileData);

        // Capture output
        ob_start();
        update_recipe();
        $output = ob_get_clean();

        // Assert the output is as expected
        $this->assertEquals(json_encode($expectedResponse), $output);
    }

    /**
     * Test update with invalid image
     */
    public function testUpdateRecipeInvalidImage()
    {
        // Mock input data
        $postData = [
            'id' => 123,
            'name' => 'Recipe With Bad Image',
            'prepare_time' => 45,
            'person_num' => 2,
            'description' => 'Description',
            'preparation' => 'Preparation steps',
            'ingredients' => json_encode(['ingredient1']),
            'genres' => json_encode(['Italian']),
            'tags' => json_encode(['Quick'])
        ];

        // Mock file upload
        $fileData = [
            'image' => [
                'name' => 'bad_file.xyz',
                'type' => 'application/octet-stream',
                'tmp_name' => '/tmp/phpXXXX',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        // Expected error response
        $expectedResponse = ['success' => false, 'message' => 'Invalid image format'];

        // Configure mock for image conversion
        $this->wejvMock->expects($this->once())
            ->method('convertToWebP')
            ->willReturn(null);

        // UpdateRecipe should not be called
        $this->wejvMock->expects($this->never())
            ->method('updateRecipe');

        // Setup the request
        $this->setupPostData($postData, $fileData);

        // Capture output
        ob_start();

        // This will call exit, so we need to use expectOutputString
        $this->expectOutputString(json_encode($expectedResponse));

        try {
            update_recipe();
        } catch (\Exception $e) {
            // We might get an exception due to the exit() call in the function
            // That's expected behavior, so we can ignore it
        }

        ob_end_clean();
    }

    /**
     * Test update recipe with empty genres and tags
     */
    public function testUpdateRecipeWithEmptyGenresAndTags()
    {
        // Mock input data with empty arrays for genres and tags
        $postData = [
            'id' => 123,
            'name' => 'Simple Recipe',
            'prepare_time' => 15,
            'person_num' => 1,
            'description' => 'Simple description',
            'preparation' => 'Simple steps',
            'ingredients' => json_encode(['simple ingredient']),
            'genres' => '[]',  // Empty array as JSON
            'tags' => '[]'     // Empty array as JSON
        ];

        // Set up expected data to be passed to updateRecipe
        $expectedData = [
            'id' => 123,
            'name' => 'Simple Recipe',
            'prepareTime' => 15,
            'personNum' => 1,
            'description' => 'Simple description',
            'preparation' => 'Simple steps',
            'ingredients' => json_encode(['simple ingredient']),
            'genres' => [],
            'tags' => []
        ];

        // Setup expected response
        $expectedResponse = ['success' => true, 'id' => 123];

        // Configure mock
        $this->wejvMock->expects($this->once())
            ->method('updateRecipe')
            ->with($this->equalTo($expectedData))
            ->willReturn($expectedResponse);

        // Setup the request
        $this->setupPostData($postData);

        // Capture output
        ob_start();
        update_recipe();
        $output = ob_get_clean();

        // Assert the output is as expected
        $this->assertEquals(json_encode($expectedResponse), $output);
    }

    /**
     * Test recipe update error handling
     */
    public function testUpdateRecipeError()
    {
        // Mock input data
        $postData = [
            'id' => 999, // Non-existent ID
            'name' => 'Error Recipe',
            'prepare_time' => 30,
            'person_num' => 4,
            'description' => 'Description',
            'preparation' => 'Preparation',
            'ingredients' => json_encode(['ingredient']),
            'genres' => json_encode(['Genre']),
            'tags' => json_encode(['Tag'])
        ];

        // Setup expected error response
        $expectedResponse = ['success' => false, 'message' => 'Recipe not found'];

        // Configure mock
        $this->wejvMock->expects($this->once())
            ->method('updateRecipe')
            ->willReturn($expectedResponse);

        // Setup the request
        $this->setupPostData($postData);

        // Capture output
        ob_start();
        update_recipe();
        $output = ob_get_clean();

        // Assert the output is as expected
        $this->assertEquals(json_encode($expectedResponse), $output);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        // Reset superglobals
        $_POST = [];
        $_FILES = [];
    }
}
