let loginFormRef;
let toggleButtonRef;
let forgotPasswordRef;
const toggleText = {
	unhidden: "Show username and password login",
	hidden: "Hide username and password login"
}

window.onload = () => {
	loginFormRef = document.querySelector("#loginform") //save reference to the login form
	forgotPasswordRef = document.querySelector("#nav") //save reference to the "Lost your password" link
	loginFormRef.style.visibility = "hidden" //hide login form
	forgotPasswordRef.style.visibility = "hidden" //hide the "Lost your password" link
	toggleButtonRef = document.querySelector("#login-form-toggle") //save reference to the toggle button
	toggleButtonRef.textContent = toggleText.unhidden //populate toggle button with text
}

function toggleLoginForm(){
	const isHidden = loginFormRef.style.visibility === "hidden"   //gets display state of login form - hidden or visible
	if(isHidden) {
		loginFormRef.style.visibility = "visible"
		forgotPasswordRef.style.visibility = "visible"
		toggleButtonRef.textContent = toggleText.hidden
	} else {
		loginFormRef.style.visibility = "hidden"
		forgotPasswordRef.style.visibility = "hidden"
		toggleButtonRef.textContent = toggleText.unhidden
	}
}
