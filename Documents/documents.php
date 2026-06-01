<?php
$aid=$_REQUEST['id']; 
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
<meta charset="utf-8">
	<title>Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link href="../photo/litebox-master/assets/css/litebox.css" rel="stylesheet" type="text/css" media="all" />
<!--header-->
<style>

body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
} 
h2{
    padding-left: 10%;
}
p {
    color: black;
    margin-left: 190px;
font-family:monospace;
    
}
.navbar {
    background-color: #002242;
    color: #fff;
   padding:3px;
   
}
section.wrapper.cl {
    margin-top: 4%;
}
img.pic-image {
    podding: initial;
 }
.nav-list a {
    text-decoration: none;
    color: #fff;
    font-size: 18px;
}
.container1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    padding-left: 25px;
    font-size: 24px;
    margin: 0;
	margin-top: 50px;
	color:white;
}

.nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
	margin-top: 55px;
}

.nav-list li {
    padding-right: 35px;
    display: inline;
    font-weight: 900;
}

.nav-list a {
    text-decoration: none;
    color: #fff;
    font-size: 18px;
}

.nav-list a:hover {
    text-decoration: underline;
}
nav.navbar {
    padding: -45px;
    height: 100px;
    margin-top: -2%;
}
table{
    border-collapse:collapse;
    width:80%;

}
td,th{
    border:1px solid #c0c0c0;
    text-align:left;
    padding: 14px;
    padding-left: 55px;
}
tr:nth-child(even){
    background-color:#f0ecec;
}
button {
    padding: 8px;
    width: 80px;
    font-size: 15px;
    border-radius: 5px;
    text-decoration: none;
	color:red;
}
button a{
color:black;
}
a{
	color:white;
    text-decoration:none;
}

</style>
</head>
<!--header-->
<body oncontextmenu = "return false" onselectstart ="return false" ondragstart="return false">
<nav class="navbar">
        <div class="container1">
             <h1 class="logo"><a href="index.php">WESEE</a></h1>
            <ul class="nav-list">
                <li><a href="../index.php">HOME</a></li>
				  <li><a href="index.php"</a>DOCUMENTS</li>
				   <li><a href="../Photo/index.php">GALLERY</a></li>
                <li><a href="../Videos/index.php">VIDEOS</a></li>
				 <li><a href="../admin/login.php">LOGIN</a></li>

    </div>
            </ul>
        </div>
    </nav>
	 
     <?php  
include 'connect.php';
error_reporting(0);
$sql = "SELECT * FROM folders where albumid='$aid'";
$rs_result = mysqli_query ($con,$sql);
while ($row = mysqli_fetch_assoc($rs_result)) 
{
$aimage=$row['image'];
$aname=$row['name'];
$adesc=$row['adesc'];
$astatus=$row['status'];
?>
<br><h2>
Document of <?php echo "$aname"; ?>
</h2>
 </div>		 
	 </div>
	 <div class="gallery-text">
		</div>
   <div class="container">
			<div class="one-whole text-center">
            <p><?php echo "$adesc"; ?></p>
			<?php } ?>
<p style="margin-left:75px">
<center><table>
<tr>
    <th>Section</th>
    <th>Title</th>
    <th>Preview</th>	
</tr>
<?php  
include "connect.php";
$con = mysqli_connect("localhost", "root", "", "s&p");
$sql = "SELECT * FROM files where aid=$aid and status='process'";
$num_rows = mysqli_num_rows(mysqli_query($con,$sql));		
$result = mysqli_query($con,$sql);
while($row = mysqli_fetch_array($result))
{
$gimage=$row['gimages'];
$title=$row['title'];
?>	
<tr>
    <td><?php echo "$aname"; ?></td>
    <td><?php echo "$title"; ?></td>
    <td><button><?php echo "<a href='../admin/fupload/$gimage#toolbar=0' target='_blank' class='inline-block litebox' >Preview</a> ";?></button></td>
   
</tr>				
<?php } ?>	
</table></center>
</div>			
</div>
<div class="clearfix"></div>

<script src="../photo/jquery.min.js"></script>
		<script src="../photo/litebox-master/assets/js/smoothscroll.min.js" type="text/javascript"></script>
	
		<script src="../photo/litebox-master/assets/js/tipsy.min.js" type="text/javascript"></script>
		<script src="../photo/litebox-master/assets/js/backbone.js" type="text/javascript"></script>
		<script src="../photo/litebox-master/assets/js/litebox.min.js" type="text/javascript"></script>

		<script type="text/javascript">
			$('.litebox').liteBox();
		</script>

</div>
</div>
</body>
</html>