<?php
namespace Cloudinary {

    $base = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
    require_once(join(DIRECTORY_SEPARATOR, array($base, 'src', 'Cloudinary.php')));
    require_once(join(DIRECTORY_SEPARATOR, array($base, 'src', 'Uploader.php')));
    require_once(join(DIRECTORY_SEPARATOR, array($base, 'src', 'Api.php')));
    require_once('TestHelper.php');
    use PHPUnit\Framework\TestCase;

    class ArchiveTest extends TestCase
    {
        /** @var string */
        protected $tag;

        public static function setUpBeforeClass()
        {
            Curl::$instance = new Curl();
        }

        public function setUp()
        {
            \Cloudinary::reset_config();
            if (!\Cloudinary::config_get("api_secret")) {
                $this->markTestSkipped('Please setup environment for Upload test to run');
            }
            $this->tag = "php_test_" . rand(11111, 99999);

            Uploader::upload("tests/logo.png", array("tags" => array($this->tag)));
            Uploader::upload("tests/logo.png", array("tags" => array($this->tag), "width" => 10, "crop" => "scale"));
        }

        public function tearDown()
        {
            Curl::$instance = new Curl();
            $api = new \Cloudinary\Api();
            $api->delete_resources_by_tag($this->tag);
        }

        public function test_create_zip()
        {
            $result = Uploader::create_zip(array("tags" => $this->tag));
            $this->assertEquals(2, $result["file_count"]);
        }

        public function test_expires_at()
        {
//        Curl::mockUpload($this);
            Uploader::create_zip(array("tags" => $this->tag, "expires_at" => time() + 3600));
            assertUrl($this, '/image/generate_archive');
            assertParam($this, "target_format", "zip");
            assertParam($this, "tags[0]", $this->tag);
            assertParam($this, "expires_at", null, "should support the 'expires_at' parameter");
        }

        public function test_skip_transformation_name()
        {
            Curl::mockUpload($this);
            Uploader::create_zip(array("tags" => $this->tag, "skip_transformation_name" => true));
            assertUrl($this, '/image/generate_archive');
            assertParam($this, "tags[0]", $this->tag);
            assertParam($this, "skip_transformation_name", 1, "should support the 'skip_transformation_name' parameter");
        }

        public function test_allow_missing()
        {
            Curl::mockUpload($this);
            Uploader::create_zip(array("tags" => $this->tag, "allow_missing" => true));
            assertUrl($this, '/image/generate_archive');
            assertParam($this, "tags[0]", $this->tag);
            assertParam($this, "allow_missing", 1, "should support the 'allow_missing' parameter");
        }

        public function test_download_zip_url()
        {
            $result = \Cloudinary::download_zip_url(array("tags" => $this->tag));
            $file = tempnam(".", "zip");
            file_put_contents($file, file_get_contents($result));
            $zip = new \ZipArchive();
            $zip->open($file);
            $this->assertEquals(2, $zip->numFiles);
            unlink($file);
        }

        public function test_create_archive_raw_public_ids()
        {
            $publicId = "archive_id_" . time();
            $resource = Uploader::create_archive(
                array("target_public_id" => $publicId, "tags" => $this->tag, "resource_type" => "image"),
                Uploader::TARGET_FORMAT_ZIP
            );
            $this->assertEquals($resource["resource_type"], "raw");
            $this->assertEquals(sprintf("%s.zip", $publicId), $resource["public_id"]);

            $resource = Uploader::create_archive(
                array("public_id" => $publicId, "tags" => $this->tag, "resource_type" => "image"),
                Uploader::TARGET_FORMAT_ZIP
            );
            $this->assertEquals($resource["resource_type"], "raw");
            $this->assertNotContains($publicId, $resource["public_id"]);
            $this->assertRegExp("/\\.zip/", $resource["public_id"]);
        }
    }
}
