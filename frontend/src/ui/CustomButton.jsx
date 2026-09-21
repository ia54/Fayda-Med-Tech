const CustomButton = ({
  children,
  variant = "primary",
  className = "",
  ...props
}) => {
  const baseStyles =
    "min-w-[175px] text-base hover:scale-95 transition-all duration-300 cursor-pointer font-lufga font-normal trucking-[0%] rounded-full py-2 px-6 duration-200 hover:opacity-80";

  const variants = {
    primary: "bg-[#0C93AA] text-[#FFFFFF]",
  };

  return (
    <button
      className={`${baseStyles} ${variants[variant]} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
};

export default CustomButton;
