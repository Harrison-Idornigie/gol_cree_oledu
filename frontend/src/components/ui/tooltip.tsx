"use client";

import React, { useState, ReactNode } from "react";

interface TooltipProps {
  children: ReactNode;
  content: ReactNode;
}

const Tooltip = ({ children, content }: TooltipProps) => {
  const [isVisible, setIsVisible] = useState(false);

  return (
    <div className="relative inline-block">
      <div
        onMouseEnter={() => setIsVisible(true)}
        onMouseLeave={() => setIsVisible(false)}
        onFocus={() => setIsVisible(true)}
        onBlur={() => setIsVisible(false)}
      >
        {children}
      </div>
      {isVisible && (
        <div className="absolute z-50 px-2 py-1 text-sm text-white transform -translate-x-1/2 bg-black rounded shadow-lg -top-8 left-1/2 whitespace-nowrap">
          {content}
          <div className="absolute w-2 h-2 transform rotate-45 -translate-x-1/2 bg-black -bottom-1 left-1/2"></div>
        </div>
      )}
    </div>
  );
};

// These are just placeholders to maintain API compatibility with the code that uses these components
const TooltipProvider = ({ children }: { children: ReactNode }) => (
  <>{children}</>
);
const TooltipTrigger = ({
  children,
  asChild,
  ...props
}: {
  children: ReactNode;
  asChild?: boolean;
  [key: string]: any;
}) => <div {...props}>{children}</div>;
const TooltipContent = ({ children }: { children: ReactNode }) => (
  <>{children}</>
);

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider };
