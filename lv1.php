<?php
require_once 'simple_html_dom.php';

//sučelje
interface iRadovi {
    public function create($data);
    public function save();
    public function read();
}

//klasa DiplomskiRadovi
class DiplomskiRadovi implements iRadovi {
    private $naziv_rada;
    private $tekst_rada;
    private $link_rada;
    private $oib_tvrtke;

    public function __construct($data) {
        $this->naziv_rada = $data['naziv_rada'] ?? 'Nepoznato';
        $this->tekst_rada = $data['tekst_rada'] ?? 'Nema opisa';
        $this->link_rada = $data['link_rada'] ?? '#';
        $this->oib_tvrtke = $data['oib_tvrtke'] ?? '00000000000';
    }

    public function create($data) {
        return new self($data);
    }

    public function save() {
        $conn = new mysqli("localhost", "root", "", "radovi");
        if ($conn->connect_error) {
            die("Greška spajanja na bazu: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO diplomski_radovi (naziv_rada, tekst_rada, link_rada, oib_tvrtke) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $this->naziv_rada, $this->tekst_rada, $this->link_rada, $this->oib_tvrtke);

        if ($stmt->execute()) {
            echo "Podaci uspješno spremljeni u bazu.<br>";
        } else {
            echo "Greška pri spremanju: " . $stmt->error . "<br>";
        }

        $stmt->close();
        $conn->close();
    }

    public function read() {
        $conn = new mysqli("localhost", "root", "", "radovi");
        if ($conn->connect_error) {
            die("Greška spajanja na bazu: " . $conn->connect_error);
        }

        $result = $conn->query("SELECT * FROM diplomski_radovi");
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<hr><b>ID:</b> " . htmlspecialchars($row["id"]) .
                     "<br><b>OIB tvrtke:</b> " . htmlspecialchars($row["oib_tvrtke"]) .
                     "<br><b>Naziv rada:</b> " . htmlspecialchars($row["naziv_rada"]) .
                     "<br><b>Link rada:</b> <a href='" . htmlspecialchars($row["link_rada"]) . "' target='_blank'>" . htmlspecialchars($row["link_rada"]) . "</a>" .
                     "<br><b>Tekst rada:</b> " . substr(htmlspecialchars($row["tekst_rada"]), 0, 300) . "...";
            }
        } else {
            echo "Nema podataka u bazi.";
        }

        $conn->close();
    }
}

//funkcija za dohvaćanje HTML-a
function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36");
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL greška: " . curl_error($ch) . "<br>";
        return false;
    }
    curl_close($ch);
    return $html;
}

//obrada stranica
for ($i = 2; $i <= 6; $i++) {
    $url = "https://stup.ferit.hr/index.php/zavrsni-radovi/page/$i/";
    echo "<br>Dohvaćam stranicu: <a href='$url' target='_blank'>$url</a><br>";

    $htmlContent = fetchHTML($url);
    if (!$htmlContent) {
        echo "Greška: Nije moguće dohvatiti sadržaj stranice.<br>";
        continue;
    }

    $html = str_get_html($htmlContent);
    if (!$html) {
        echo "Greška: HTML nije ispravno parsiran! Provjerite Simple HTML DOM ili sadržaj stranice.<br>";
        echo "<pre>" . htmlspecialchars(substr($htmlContent, 0, 1000)) . "</pre><br>";
        continue;
    }

    foreach ($html->find('article') as $article) {
        //dohvaćanje naslova
        $naslov = $article->find('h2.entry-title a', 0) ? trim($article->find('h2.entry-title a', 0)->plaintext) : 'Nepoznato';

        //dohvaćanje linka
        $link = $article->find('h2.entry-title a', 0) ? $article->find('h2.entry-title a', 0)->href : '#';

        //dohvaćanje teksta
        $tekst = $article->find('div.entry-content p', 0) ? trim($article->find('div.entry-content p', 0)->plaintext) : 'Nema opisa';

        //dohvaćanje OIB-a iz slike
        $img = $article->find('img.wppost-image', 0);
        $oib = '00000000000';
        if ($img && preg_match('/(\d{11})\.(png|jpg|jpeg)/', $img->src, $matches)) {
            $oib = $matches[1];
        }

        $data = [
            'naziv_rada' => $naslov,
            'tekst_rada' => $tekst,
            'link_rada' => $link,
            'oib_tvrtke' => $oib
        ];

        $rad = (new DiplomskiRadovi([]))->create($data);
        $rad->save();
    }

    $html->clear(); //oslobađanje memorije
}

echo "<h2>Pohranjeni diplomski radovi:</h2>";
$diplomskiRad = new DiplomskiRadovi([]);
$diplomskiRad->read();

?>