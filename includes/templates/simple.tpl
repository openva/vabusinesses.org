<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>{$browser_title}</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/mini-default.min.css" />
	</head>
	
	<body>
	<header>
		<h1><a href="/" rel="home">Virginia Businesses</a></h1>
		<nav>
			<a href="/">Home</a>
			<a href="/search/">Search</a>
			<a href="/data/">Data</a>
		</nav>
	</header>
	<main>

		<h1>{$page_title}</h1>

		<article id="search">
			<form method="get" action="/search/">
				<label for="query">Search</label>
				<input type="text" size="50" name="query" id="query">
				<input type="submit" value="Go">
			</form>
		</article>
        
        {$page_body}

		<footer class="wrapper">
			All business records are created by the Virginia State Corporation Commission, and
			are thus without copyright protection, so may be reused and reproduced freely,
			without seeking any form of permission from anybody. All other website content is
			published under <a href="http://opensource.org/licenses/MIT">the MIT license</a>.
			This website is an independent, private effort, created and run as a hobby, and is
			in no way affiliated with the Commonwealth of Virginia or the State Corporation
			Commission. <a href="https://github.com/openva/vabusinesses.org">All site source
			code is on GitHub</a>—pull requests welcome.
		</footer>

	</main>
	</body>
</html>