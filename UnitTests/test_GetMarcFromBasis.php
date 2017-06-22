<?

$her = getcwd();
echo "her:$her<br/>\n";
require_once "inc/OPAC_class_lib/GetMarcFromBasis_class.php";

class testGetMarcFromBasis extends \PHPUnit_Framework_TestCase {


    public function testGetMultVolume() {

        $getM = new GetMarcFromBasis('workdir', 'sebasis', 'sebasis', 'dora11.dbc.dk');

        echo "\n--- test ---\n\n";
        $this->assertNotEquals("aa", "bb");
    }

}
