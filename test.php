<?php
ini_set('max_execution_time', 300);
?>

<html>
<head>
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">

<!-- Optional theme -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css">
<style>
ul { margin: 0; padding: 0;}
ul li { list-style-type: none; }
</style>
</head>
<body>
<div class="container">
	<div class="row">
 		<div class="col-md-12">
			<h1>iubenda class test</h1>
			<form action="" method="POST">
				<strong>URL WEBSITE</strong><BR>
				<input type="text" name="url">
				<input type="submit" class="btn" value="Analyze">
			</form>
		</div>
	
<?php
	
	$url = $_POST['url'];
	if($url || $_GET['url']){
		
	function print_stuff($array){
		if(count($array)){
			echo "<ul>";
			foreach($array as $r){
				echo "<li><pre><code>".htmlspecialchars($r)."</code></pre></li>";
			}
		echo "</ul>";
		}else{
			echo "<p>Nothing</p>";
		}
	}
	
	echo '<div class="col-md-12" style="padding-bottom:150px;"><h2>RESULTS</H2>';
	include_once 'iubenda.class.php';
	include_once 'simple_html_dom.php';
	
	if($_GET['url']){
		$content = file_get_contents('A');
	}else{
		$content = file_get_contents($url);
	}
	
	$page = new Page($content);
	$page->parse();

	$iub_comments_detected = count($page->iub_comments_detected);
	$scripts_detected = count($page->scripts_detected);
	$iframe_detected = count($page->iframe_detected);
	$iframe_converted = count($page->iframe_converted);
	$script_inline_converted = count($page->scripts_inline_converted);
	$script_converted = count($page->scripts_converted);
	
	echo "<p>Iubenda comments detected: $iub_comments_detected<br>Iubenda automatic stuff<br>Iframe detected: $iframe_detected<br>Iframe autoconverted: $iframe_converted<br>Scripts detected: $scripts_detected<br>Inline scripts autoconverted: $script_inline_converted<br>Scripts autoconverted: $script_converted</p>";
	

	
	
	
	echo "<H3>DETAILS</H3>";
	
	echo "<H4>iubenda comments stuff</h4>";
	print_stuff($page->iub_comments_detected);
		
	echo "<H4>Script detected</h4>";
	print_stuff($page->scripts_detected);
	echo "<H4>Script converted</h4>";
	print_stuff($page->scripts_converted);
	echo "<H4>Script inline converted</h4>";
	print_stuff($page->scripts_inline_converted);
	
	echo "<H4>Iframe detected</h4>";
	print_stuff($page->iframe_detected);
	echo "<H4>Iframe converted</h4>";
	print_stuff($page->iframe_converted);
	
	
	
	echo "</div>";
	
	
	}
?>
</div>
</div>

</body>
</html>